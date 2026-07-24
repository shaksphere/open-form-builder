<?php
defined( 'ABSPATH' ) || exit;

/**
 * Session-picker slot storage and capacity logic.
 *
 * Slots are authored in the builder but persisted to the ofb_slots table, which
 * is the source of truth for capacity and the live booked_count. Depletion is
 * intentionally first-pay-wins: a slot is consumed only on payment success
 * (via OFB_Slots::book_for_submission, called from the Stripe webhook), never at
 * selection time. If a confirming payment would push a slot past capacity, the
 * booking still records but the submission is flagged for staff.
 */
class OFB_Slots {

	/**
	 * Replace a form's slot definitions. Existing booked_count is preserved for
	 * slots whose slot_key is unchanged, so editing labels/timings never wipes
	 * live bookings. Slots no longer present are removed.
	 *
	 * @param array $slots List of [slot_key, tab, label, capacity, sort_order].
	 */
	public static function save_for_form( int $form_id, array $slots ): void {
		global $wpdb;
		$table = OFB_DB::slots();

		$existing = self::for_form( $form_id );
		$by_key   = [];
		foreach ( $existing as $row ) {
			$by_key[ $row['slot_key'] ] = $row;
		}

		$seen = [];
		$order = 0;
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$key = OFB_Schema::clean_id( $slot['slot_key'] ?? '', '' );
			if ( '' === $key ) {
				continue;
			}
			$seen[] = $key;
			$day        = self::clean_day( $slot['day'] ?? '' );
			$time_label = sanitize_text_field( (string) ( $slot['time_label'] ?? '' ) );
			$label = (string) ( $slot['label'] ?? '' );
			// Day + time are authoritative: rebuild the display label from them when
			// present, so builder edits to day/time stay reflected in the label.
			if ( '' !== $day || '' !== $time_label ) {
				$label = trim( self::day_label( $day ) . ' ' . $time_label );
			}

			$data = [
				'form_id'    => $form_id,
				'slot_key'   => $key,
				'tab'        => OFB_Schema::clean_id( $slot['tab'] ?? '', '' ),
				'label'      => sanitize_text_field( $label ),
				'teacher'    => sanitize_text_field( (string) ( $slot['teacher'] ?? '' ) ),
				'day'        => $day,
				'start_min'  => isset( $slot['start_min'] ) ? (int) $slot['start_min'] : self::parse_minutes( $time_label ),
				'time_label' => $time_label,
				'exceptions' => wp_json_encode( self::clean_exceptions( $slot['exceptions'] ?? [] ) ),
				'capacity'   => max( 0, (int) ( $slot['capacity'] ?? 0 ) ),
				'sort_order' => $order++,
			];
			$formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d' ];
			if ( isset( $by_key[ $key ] ) ) {
				$wpdb->update( $table, $data, [ 'id' => $by_key[ $key ]['id'] ], $formats, [ '%d' ] );
			} else {
				$data['booked_count'] = 0;
				$formats[] = '%d';
				$wpdb->insert( $table, $data, $formats );
			}
		}

		// Remove slots that were deleted in the builder.
		foreach ( $by_key as $key => $row ) {
			if ( ! in_array( $key, $seen, true ) ) {
				$wpdb->delete( $table, [ 'id' => $row['id'] ], [ '%d' ] );
			}
		}
	}

	/** @return array<int,array> slot rows for a form, ordered. */
	public static function for_form( int $form_id ): array {
		global $wpdb;
		$table = OFB_DB::slots();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %d ORDER BY sort_order ASC, id ASC", $form_id ),
			ARRAY_A
		);
		return array_map( [ self::class, 'cast' ], is_array( $rows ) ? $rows : [] );
	}

	/** Map slot_key => row for quick lookup during render/booking. */
	public static function map_for_form( int $form_id ): array {
		$out = [];
		foreach ( self::for_form( $form_id ) as $row ) {
			$out[ $row['slot_key'] ] = $row;
		}
		return $out;
	}

	public static function is_full( array $slot ): bool {
		return $slot['capacity'] > 0 && $slot['booked_count'] >= $slot['capacity'];
	}

	/**
	 * Consume slots for a paid submission (called from the Stripe webhook).
	 * Atomically increments booked_count and records a booking row per slot.
	 *
	 * @param string[] $slot_keys Slot keys the visitor selected.
	 * @return bool True if every slot stayed within capacity; false means at
	 *              least one slot overflowed and the submission should be flagged.
	 */
	public static function book_for_submission( int $form_id, int $submission_id, array $slot_keys ): bool {
		global $wpdb;
		$slots_table    = OFB_DB::slots();
		$bookings_table = OFB_DB::bookings();
		$all_ok = true;

		foreach ( array_unique( $slot_keys ) as $key ) {
			$key = OFB_Schema::clean_id( $key, '' );
			if ( '' === $key ) {
				continue;
			}
			$slot = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$slots_table} WHERE form_id = %d AND slot_key = %s", $form_id, $key ),
				ARRAY_A
			);
			if ( ! $slot ) {
				continue;
			}
			$slot_id = (int) $slot['id'];

			// Skip if this submission already booked this slot (webhook retries).
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$bookings_table} WHERE submission_id = %d AND slot_id = %d",
				$submission_id, $slot_id
			) );
			if ( $exists ) {
				continue;
			}

			// Conditional atomic increment: only bumps when still under capacity
			// (capacity 0 = unlimited). The first confirming payment wins the seat.
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$slots_table} SET booked_count = booked_count + 1
				 WHERE id = %d AND ( capacity = 0 OR booked_count < capacity )",
				$slot_id
			) );

			// Always record the booking for the audit trail; flag if it overflowed.
			$wpdb->insert(
				$bookings_table,
				[ 'submission_id' => $submission_id, 'slot_id' => $slot_id, 'created_at' => current_time( 'mysql' ) ],
				[ '%d', '%d', '%s' ]
			);

			if ( ! $claimed ) {
				$all_ok = false;
			}
		}

		return $all_ok;
	}

	private static function cast( array $r ): array {
		$exceptions = json_decode( (string) ( $r['exceptions'] ?? '' ), true );
		return [
			'id'           => (int) $r['id'],
			'form_id'      => (int) $r['form_id'],
			'slot_key'     => (string) $r['slot_key'],
			'tab'          => (string) $r['tab'],
			'label'        => (string) $r['label'],
			'teacher'      => (string) ( $r['teacher'] ?? '' ),
			'day'          => (string) ( $r['day'] ?? '' ),
			'start_min'    => (int) ( $r['start_min'] ?? 0 ),
			'time_label'   => (string) ( $r['time_label'] ?? '' ),
			'exceptions'   => is_array( $exceptions ) ? $exceptions : [],
			'capacity'     => (int) $r['capacity'],
			'booked_count' => (int) $r['booked_count'],
			'sort_order'   => (int) $r['sort_order'],
		];
	}

	/** Weekday keys in display order. */
	const DAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	public static function clean_day( $day ): string {
		$day = strtolower( substr( preg_replace( '/[^a-zA-Z]/', '', (string) $day ), 0, 3 ) );
		return in_array( $day, self::DAYS, true ) ? $day : '';
	}

	public static function day_label( string $day ): string {
		$map = [
			'mon' => __( 'Monday', 'open-form-builder' ),
			'tue' => __( 'Tuesday', 'open-form-builder' ),
			'wed' => __( 'Wednesday', 'open-form-builder' ),
			'thu' => __( 'Thursday', 'open-form-builder' ),
			'fri' => __( 'Friday', 'open-form-builder' ),
			'sat' => __( 'Saturday', 'open-form-builder' ),
			'sun' => __( 'Sunday', 'open-form-builder' ),
		];
		return $map[ $day ] ?? ucfirst( $day );
	}

	/** Parse a leading "5:00" / "5:00 pm" into minutes-from-midnight for sorting. */
	public static function parse_minutes( string $time_label ): int {
		if ( ! preg_match( '/(\d{1,2}):(\d{2})\s*([ap]m)?/i', $time_label, $m ) ) {
			return 0;
		}
		$h = (int) $m[1];
		$min = (int) $m[2];
		$ap = strtolower( $m[3] ?? '' );
		if ( 'pm' === $ap && 12 !== $h ) { $h += 12; }
		if ( 'am' === $ap && 12 === $h ) { $h = 0; }
		return $h * 60 + $min;
	}

	/** Sanitize an array of ISO dates (YYYY-MM-DD) used as per-slot blackouts. */
	public static function clean_exceptions( $dates ): array {
		if ( ! is_array( $dates ) ) {
			return [];
		}
		$out = [];
		foreach ( $dates as $d ) {
			$d = trim( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				$out[] = $d;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Human label including teacher + any blackout dates (for emails/submissions). */
	public static function display_label( array $slot ): string {
		$label = (string) ( $slot['label'] ?? '' );
		$teacher = trim( (string) ( $slot['teacher'] ?? '' ) );
		if ( '' !== $teacher ) {
			$label .= ' — ' . $teacher;
		}
		$excl = self::clean_exceptions( $slot['exceptions'] ?? [] );
		if ( ! empty( $excl ) ) {
			sort( $excl );
			$fmt = array_map( fn( $d ) => date_i18n( 'j M', strtotime( $d ) ), $excl );
			/* translators: %s: comma-separated excluded dates */
			$label .= ' ' . sprintf( __( '(not on %s)', 'open-form-builder' ), implode( ', ', $fmt ) );
		}
		return $label;
	}
}
