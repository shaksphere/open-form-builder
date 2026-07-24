<?php
defined( 'ABSPATH' ) || exit;

/**
 * Capability checks, nonce actions and per-form settings sanitization.
 *
 * Two trust boundaries:
 *  - Admin builder (manage_options): may save schema + settings, including the
 *    per-form custom JS field. The schema is normalized by OFB_Schema; the
 *    settings are sanitized here.
 *  - Public submit (no auth): validated by a per-form nonce; the field set is
 *    always re-derived server-side from the stored schema, never trusted from
 *    the request body.
 */
class OFB_Security {

	const NONCE_SUBMIT = 'ofb_submit';   // public front-end submit
	const NONCE_ADMIN  = 'wp_rest';      // REST builder calls use the cookie nonce

	/** Who may build/manage forms and read submissions. */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sanitize a per-form settings blob from the builder.
	 *
	 * Note: Stripe API keys are deliberately NOT stored here — they live in the
	 * site-wide option (OFB_Stripe::OPTION) so secrets never sit inside per-form
	 * JSON that gets exported/imported.
	 */
	public static function sanitize_settings( $in, bool $allow_js ): array {
		$in = is_array( $in ) ? $in : [];

		return [
			'pricing'        => self::sanitize_pricing( $in['pricing'] ?? [] ),
			'session_picker' => self::sanitize_session_picker( $in['session_picker'] ?? [] ),
			'payments'       => self::sanitize_payments( $in['payments'] ?? [] ),
			'emails'         => self::sanitize_emails( $in['emails'] ?? [] ),
			'marketing'      => self::sanitize_marketing( $in['marketing'] ?? [] ),
			'theme'          => self::sanitize_theme( $in['theme'] ?? [] ),
			'sheet_export'   => self::sanitize_sheet_export( $in['sheet_export'] ?? [] ),
			'redirects'      => [
				'thank_you_url' => esc_url_raw( (string) ( $in['redirects']['thank_you_url'] ?? '' ) ),
			],
			'messages'       => [
				'success' => sanitize_text_field( (string) ( $in['messages']['success'] ?? '' ) ),
			],
			'custom_js'      => self::sanitize_custom_js( (string) ( $in['custom_js'] ?? '' ), $allow_js ),
		];
	}

	private static function sanitize_pricing( $p ): array {
		$p = is_array( $p ) ? $p : [];
		$discount_type = ( ( $p['block_discount']['type'] ?? 'amount' ) === 'percent' ) ? 'percent' : 'amount';
		$mode = ( ( $p['mode'] ?? 'sessions' ) === 'options' ) ? 'options' : 'sessions';
		return [
			'enabled'             => ! empty( $p['enabled'] ),
			'mode'                => $mode,
			'base_fee'            => self::money( $p['base_fee'] ?? 0 ),
			'base_price'          => self::money( $p['base_price'] ?? 0 ),
			'base_sessions'       => max( 0, (int) ( $p['base_sessions'] ?? 0 ) ),
			'extra_session_price' => self::money( $p['extra_session_price'] ?? 0 ),
			'block_size'          => max( 1, (int) ( $p['block_size'] ?? 1 ) ),
			'block_discount'      => [
				'type'  => $discount_type,
				'value' => self::money( $p['block_discount']['value'] ?? 0 ),
			],
		];
	}

	private static function sanitize_session_picker( $s ): array {
		$s = is_array( $s ) ? $s : [];
		$max = ( isset( $s['max'] ) && '' !== $s['max'] && null !== $s['max'] ) ? max( 0, (int) $s['max'] ) : null;
		return [
			'min'     => isset( $s['min'] ) ? max( 0, (int) $s['min'] ) : 4,
			'max'     => $max,
			'periods' => self::sanitize_periods( $s['periods'] ?? [] ),
		];
	}

	/**
	 * Periods define the tabs of the session picker and the custom date window(s)
	 * each period runs between. Shape: [ {key,label,ranges:[{start,end}]} ].
	 */
	private static function sanitize_periods( $periods ): array {
		if ( ! is_array( $periods ) ) {
			return [];
		}
		$out = [];
		foreach ( array_slice( $periods, 0, 20 ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$key = OFB_Schema::clean_id( $p['key'] ?? '', '' );
			if ( '' === $key ) {
				continue;
			}
			$ranges = [];
			foreach ( ( is_array( $p['ranges'] ?? null ) ? $p['ranges'] : [] ) as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$start = self::clean_date( $r['start'] ?? '' );
				$end   = self::clean_date( $r['end'] ?? '' );
				if ( '' !== $start || '' !== $end ) {
					$ranges[] = [ 'start' => $start, 'end' => $end ];
				}
			}
			$out[] = [
				'key'    => $key,
				'label'  => sanitize_text_field( (string) ( $p['label'] ?? $key ) ),
				'ranges' => $ranges,
			];
		}
		return $out;
	}

	private static function clean_date( $d ): string {
		$d = trim( (string) $d );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '';
	}

	private static function sanitize_payments( $p ): array {
		$p = is_array( $p ) ? $p : [];
		$currency = strtolower( sanitize_text_field( (string) ( $p['currency'] ?? 'aud' ) ) );
		$currency = preg_match( '/^[a-z]{3}$/', $currency ) ? $currency : 'aud';
		return [
			'enabled'       => ! empty( $p['enabled'] ),
			'currency'      => $currency,
			'product_label' => sanitize_text_field( (string) ( $p['product_label'] ?? __( 'Sessions', 'open-form-builder' ) ) ),
		];
	}

	private static function sanitize_emails( $e ): array {
		$e = is_array( $e ) ? $e : [];

		$mail = function ( $m ) {
			$m = is_array( $m ) ? $m : [];
			return [
				'enabled' => ! empty( $m['enabled'] ),
				'to'      => sanitize_text_field( (string) ( $m['to'] ?? '' ) ),     // may contain {tags}; resolved later
				'subject' => sanitize_text_field( (string) ( $m['subject'] ?? '' ) ),
				'body'    => wp_kses_post( (string) ( $m['body'] ?? '' ) ),
			];
		};

		// Conditional send-to routing based on one field's value (CF7-style).
		$routing = is_array( $e['routing'] ?? null ) ? $e['routing'] : [];
		$map = [];
		foreach ( ( is_array( $routing['map'] ?? null ) ? $routing['map'] : [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$val   = sanitize_text_field( (string) ( $row['value'] ?? '' ) );
			$email = sanitize_email( (string) ( $row['email'] ?? '' ) );
			if ( '' !== $val && is_email( $email ) ) {
				$map[] = [ 'value' => $val, 'email' => $email ];
			}
		}

		return [
			'confirmation' => $mail( $e['confirmation'] ?? [] ),
			'receipt'      => $mail( $e['receipt'] ?? [] ),
			'routing'      => [
				'field'   => OFB_Schema::clean_name( $routing['field'] ?? '' ),
				'map'     => $map,
				'default' => sanitize_email( (string) ( $routing['default'] ?? '' ) ),
			],
		];
	}

	/**
	 * Email-marketing sync settings. Per-form: which provider, which audience/group,
	 * and the field mapping. The provider API keys themselves are site-wide (kept in
	 * the Stripe option) so secrets never sit inside exportable per-form JSON —
	 * mirrors the Stripe-keys note above.
	 */
	private static function sanitize_marketing( $m ): array {
		$m = is_array( $m ) ? $m : [];
		$provider = in_array( $m['provider'] ?? '', [ 'mailchimp', 'mailerlite' ], true ) ? $m['provider'] : 'mailchimp';
		return [
			'enabled'     => ! empty( $m['enabled'] ),
			'provider'    => $provider,
			'list_id'     => sanitize_text_field( (string) ( $m['list_id'] ?? '' ) ),
			'email_field' => OFB_Schema::clean_name( $m['email_field'] ?? '' ),
			'name_field'  => OFB_Schema::clean_name( $m['name_field'] ?? '' ),
			'tags'        => sanitize_text_field( (string) ( $m['tags'] ?? '' ) ),
			'double_optin' => ! empty( $m['double_optin'] ),
		];
	}

	private static function sanitize_sheet_export( $s ): array {
		$s = is_array( $s ) ? $s : [];
		return [
			'enabled'     => ! empty( $s['enabled'] ),
			'webhook_url' => esc_url_raw( (string) ( $s['webhook_url'] ?? '' ) ),
		];
	}

	/** Per-form custom JS: only kept for capable users; closing tag neutralized. */
	private static function sanitize_custom_js( string $js, bool $allow_js ): string {
		if ( ! $allow_js || '' === trim( $js ) ) {
			return '';
		}
		if ( strlen( $js ) > 50000 ) {
			$js = substr( $js, 0, 50000 );
		}
		return str_ireplace( '</script', '<\/script', $js );
	}

	/**
	 * Branding: the handful of theme knobs the renderer exposes as CSS custom
	 * properties on the form wrapper (see OFB_Renderer::render). Kept intentionally
	 * small — colors + corner radius — rather than a full theme/CSS system.
	 */
	private static function sanitize_theme( $t ): array {
		$t = is_array( $t ) ? $t : [];
		return [
			'primary'    => self::clean_hex( $t['primary'] ?? '', '#2563eb' ),
			'text'       => self::clean_hex( $t['text'] ?? '', '#111827' ),
			'background' => self::clean_hex( $t['background'] ?? '', '#ffffff' ),
			'radius'     => max( 0, min( 32, (int) ( $t['radius'] ?? 10 ) ) ),
		];
	}

	private static function clean_hex( $v, string $fallback ): string {
		$v = trim( (string) $v );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : $fallback;
	}

	/** Non-negative money value kept as a float with cent precision. */
	public static function money( $v ): float {
		$v = is_numeric( $v ) ? (float) $v : 0.0;
		return $v < 0 ? 0.0 : round( $v, 2 );
	}
}
