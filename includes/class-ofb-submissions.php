<?php
defined( 'ABSPATH' ) || exit;

/**
 * Submission lifecycle.
 *
 * Flow:
 *   1. validate()        - re-derive the schema, evaluate conditional visibility
 *                          server-side, validate only visible fields, build the
 *                          clean data array and resolve the session count/price.
 *   2. create_pending()  - persist as `pending`.
 *   3. mark_paid()       - called from the Stripe webhook (or directly for
 *                          free/no-payment forms): set status, deplete slots,
 *                          fire emails + sheet export. Flags the submission if a
 *                          slot overflowed (first-pay-wins).
 *
 * The field set is always taken from the stored schema — the request's own field
 * list is never trusted.
 */
class OFB_Submissions {

	const STATUS_PENDING = 'pending';
	const STATUS_PAID    = 'paid';
	const STATUS_FREE    = 'complete'; // no-payment forms

	/**
	 * Validate raw posted fields against a form and build clean data.
	 *
	 * @return array{
	 *   ok:bool, errors:array<string,string>, data:array,
	 *   session_keys:string[], sessions:int, amount:float
	 * }
	 */
	public static function validate( array $form, array $raw ): array {
		$schema   = $form['schema'];
		$settings = $form['settings'];
		$form_id  = (int) ( $form['id'] ?? 0 );
		$fields   = OFB_Schema::fields_by_name( $schema );

		// First pass: collect raw scalar/array values keyed by name so the
		// conditional evaluator can see sibling answers.
		$values = [];
		foreach ( $fields as $name => $field ) {
			if ( ! OFB_Schema::is_input_type( $field['type'] ) ) {
				continue;
			}
			$values[ $name ] = isset( $raw[ $name ] ) ? wp_unslash( $raw[ $name ] ) : '';
		}

		$errors       = [];
		$data         = [];
		$session_keys = [];

		foreach ( $fields as $name => $field ) {
			if ( ! OFB_Schema::is_input_type( $field['type'] ) ) {
				continue;
			}
			// Skip hidden fields entirely: not validated, not stored, not emailed.
			if ( ! OFB_Schema::is_visible( $field['conditional'], $values ) ) {
				continue;
			}

			$type = $field['type'];
			$raw_value = $values[ $name ];

			if ( 'session_picker' === $type ) {
				$result = self::clean_sessions( $field, $settings, $form_id, $raw_value );
				if ( $result['error'] ) {
					$errors[ $name ] = $result['error'];
				}
				$session_keys = $result['keys'];
				$data[ $name ] = [ 'label' => $field['label'] ?: $name, 'value' => $result['labels'] ];
				continue;
			}

			$value = self::clean_value( $type, $raw_value );

			$empty = is_array( $value ) ? ( 0 === count( $value ) ) : ( '' === trim( (string) $value ) );
			if ( ! empty( $field['required'] ) && $empty ) {
				$errors[ $name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'open-form-builder' ),
					$field['label'] ?: $name
				);
				continue;
			}
			if ( 'email' === $type && ! $empty && ! is_email( (string) $value ) ) {
				$errors[ $name ] = __( 'Please enter a valid email address.', 'open-form-builder' );
			}

			$data[ $name ] = [ 'label' => $field['label'] ?: $name, 'value' => $value ];
		}

		$sessions = count( $session_keys );
		$amount   = OFB_Pricing::total( is_array( $settings['pricing'] ?? null ) ? $settings['pricing'] : [], $sessions );

		return [
			'ok'           => empty( $errors ),
			'errors'       => $errors,
			'data'         => $data,
			'session_keys' => $session_keys,
			'sessions'     => $sessions,
			'amount'       => $amount,
		];
	}

	private static function clean_value( string $type, $raw ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( (string) $raw );
			case 'textarea':
				return sanitize_textarea_field( (string) $raw );
			case 'number':
				return is_numeric( $raw ) ? $raw + 0 : '';
			case 'checkbox':
				$arr = is_array( $raw ) ? $raw : ( '' === $raw ? [] : [ $raw ] );
				return array_values( array_map( 'sanitize_text_field', array_map( 'strval', $arr ) ) );
			default:
				return sanitize_text_field( (string) $raw );
		}
	}

	/**
	 * Validate and clean a session-picker selection against capacity and the
	 * min/max rules. Returns selected keys, their human labels, and any error.
	 *
	 * @return array{keys:string[],labels:string[],error:string}
	 */
	private static function clean_sessions( array $field, array $settings, int $form_id, $raw ): array {
		$selected = is_array( $raw ) ? array_map( 'strval', $raw ) : ( '' === $raw ? [] : [ (string) $raw ] );
		$selected = array_values( array_unique( array_filter( array_map( fn( $k ) => OFB_Schema::clean_id( $k, '' ), $selected ) ) ) );

		$slot_map = OFB_Slots::map_for_form( $form_id );

		$keys   = [];
		$labels = [];
		foreach ( $selected as $key ) {
			if ( ! isset( $slot_map[ $key ] ) ) {
				continue; // unknown slot; drop silently
			}
			$slot = $slot_map[ $key ];
			// A full slot can't be selected (front end disables it; enforce here too).
			if ( OFB_Slots::is_full( $slot ) ) {
				return [ 'keys' => [], 'labels' => [], 'error' => sprintf(
					/* translators: %s: slot label */
					__( 'The session "%s" is no longer available.', 'open-form-builder' ),
					$slot['label']
				) ];
			}
			$keys[]   = $key;
			$labels[] = OFB_Slots::display_label( $slot );
		}

		// Min/max: field config wins, falling back to form-level session settings.
		$cfg   = is_array( $field['config'] ?? null ) ? $field['config'] : [];
		$sp    = is_array( $settings['session_picker'] ?? null ) ? $settings['session_picker'] : [];
		$min   = isset( $cfg['min'] ) ? (int) $cfg['min'] : (int) ( $sp['min'] ?? 4 );
		$max   = array_key_exists( 'max', $cfg ) ? $cfg['max'] : ( $sp['max'] ?? null );
		$count = count( $keys );

		$error = '';
		if ( ! empty( $field['required'] ) || $count > 0 ) {
			if ( $count < $min ) {
				$error = sprintf(
					/* translators: %d: minimum number of sessions */
					_n( 'Please select at least %d session.', 'Please select at least %d sessions.', $min, 'open-form-builder' ),
					$min
				);
			} elseif ( null !== $max && $count > (int) $max ) {
				$error = sprintf(
					/* translators: %d: maximum number of sessions */
					_n( 'Please select no more than %d session.', 'Please select no more than %d sessions.', (int) $max, 'open-form-builder' ),
					(int) $max
				);
			}
		}

		return [ 'keys' => $keys, 'labels' => $labels, 'error' => $error ];
	}

	/**
	 * Persist a pending submission.
	 *
	 * @return int submission id, or 0 on failure.
	 */
	public static function create_pending( int $form_id, array $data, array $session_keys, float $amount, string $currency ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$ok = $wpdb->insert(
			OFB_DB::submissions(),
			[
				'form_id'        => $form_id,
				'data'           => wp_json_encode( [ 'fields' => $data, 'session_keys' => array_values( $session_keys ) ] ),
				'amount_cents'   => OFB_Pricing::to_cents( $amount ),
				'currency'       => $currency,
				'payment_status' => self::STATUS_PENDING,
				'stripe_ref'     => '',
				'created_at'     => $now,
				'updated_at'     => $now,
			],
			[ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function set_stripe_ref( int $submission_id, string $ref ): void {
		global $wpdb;
		$wpdb->update(
			OFB_DB::submissions(),
			[ 'stripe_ref' => $ref, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $submission_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = OFB_DB::submissions();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	public static function find_by_stripe_ref( string $ref ): ?array {
		global $wpdb;
		$table = OFB_DB::submissions();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE stripe_ref = %s", $ref ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Mark a submission complete and run all post-payment side effects exactly
	 * once: deplete slots, send emails, export to the sheet. Idempotent — a second
	 * call (e.g. webhook retry) is a no-op for an already-finalized submission.
	 *
	 * @param string $status STATUS_PAID or STATUS_FREE.
	 */
	public static function finalize( int $submission_id, string $status ): void {
		global $wpdb;
		$sub = self::get( $submission_id );
		if ( ! $sub ) {
			return;
		}
		// Idempotency guard: only act on a still-pending submission.
		if ( self::STATUS_PENDING !== $sub['payment_status'] ) {
			return;
		}

		$form = OFB_Forms::get( $sub['form_id'] );
		if ( ! $form ) {
			return;
		}

		// Deplete slots (first-pay-wins). Overflow flags the submission.
		$flagged = 0;
		if ( ! empty( $sub['session_keys'] ) ) {
			$ok = OFB_Slots::book_for_submission( $sub['form_id'], $submission_id, $sub['session_keys'] );
			$flagged = $ok ? 0 : 1;
		}

		$wpdb->update(
			OFB_DB::submissions(),
			[ 'payment_status' => $status, 'flagged' => $flagged, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $submission_id ],
			[ '%s', '%d', '%s' ],
			[ '%d' ]
		);

		$extra = [
			'submission_id' => $submission_id,
			'form_id'       => $sub['form_id'],
			'amount'        => $sub['amount_cents'] / 100,
			'currency'      => $sub['currency'],
			'status'        => $status,
		];

		OFB_Emails::send_on_success( $form['settings'], $sub['data'], $extra );
		OFB_Sheets::export( $form['settings'], $sub['data'], $extra );

		do_action( 'ofb_submission_finalized', $submission_id, $sub, $form, $flagged );
	}

	private static function hydrate( array $row ): array {
		$decoded = json_decode( (string) $row['data'], true );
		$decoded = is_array( $decoded ) ? $decoded : [];
		return [
			'id'             => (int) $row['id'],
			'form_id'        => (int) $row['form_id'],
			'data'           => is_array( $decoded['fields'] ?? null ) ? $decoded['fields'] : [],
			'session_keys'   => is_array( $decoded['session_keys'] ?? null ) ? $decoded['session_keys'] : [],
			'amount_cents'   => (int) $row['amount_cents'],
			'currency'       => (string) $row['currency'],
			'payment_status' => (string) $row['payment_status'],
			'stripe_ref'     => (string) $row['stripe_ref'],
			'flagged'        => (int) $row['flagged'],
			'created_at'     => (string) $row['created_at'],
		];
	}

	/** @return array<int,array> recent submissions for a form (admin listing). */
	public static function for_form( int $form_id, int $limit = 200 ): array {
		global $wpdb;
		$table = OFB_DB::submissions();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %d ORDER BY created_at DESC LIMIT %d", $form_id, $limit ),
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], is_array( $rows ) ? $rows : [] );
	}
}
