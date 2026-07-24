<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sends form emails through wp_mail() so the site's SMTP plugin handles actual
 * delivery. Two messages on payment success: a confirmation (to the resolved
 * recipient, e.g. the parent or a routed staff inbox) and a receipt. Subjects
 * and bodies support {personalisation} tags.
 *
 * Conditional routing (CF7-style): the confirmation "send-to" can vary based on
 * the value of one chosen field.
 */
class OFB_Emails {

	/**
	 * @param array $settings Form settings (emails block).
	 * @param array $data     Submission data: name => [label, value].
	 * @param array $extra    submission_id, amount, currency.
	 */
	public static function send_on_success( array $settings, array $data, array $extra ): void {
		$emails = is_array( $settings['emails'] ?? null ) ? $settings['emails'] : [];

		self::send_confirmation( $emails, $data, $extra );
		self::send_receipt( $emails, $data, $extra );
	}

	private static function send_confirmation( array $emails, array $data, array $extra ): void {
		$conf = is_array( $emails['confirmation'] ?? null ) ? $emails['confirmation'] : [];
		if ( empty( $conf['enabled'] ) ) {
			return;
		}

		$to = self::resolve_recipient( $emails, $conf, $data );
		if ( ! $to ) {
			return;
		}

		$subject = OFB_Tags::replace( (string) ( $conf['subject'] ?? '' ), $data, $extra );
		$body    = OFB_Tags::replace( (string) ( $conf['body'] ?? '' ), $data, $extra );
		self::mail( $to, $subject, $body );
	}

	private static function send_receipt( array $emails, array $data, array $extra ): void {
		$receipt = is_array( $emails['receipt'] ?? null ) ? $emails['receipt'] : [];
		if ( empty( $receipt['enabled'] ) ) {
			return;
		}

		// Receipt goes to the submitter: prefer an explicit {tag}, else first email field.
		$to = OFB_Tags::replace( (string) ( $receipt['to'] ?? '' ), $data, $extra );
		$to = sanitize_email( $to );
		if ( ! $to ) {
			$to = self::first_email_value( $data );
		}
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

		$subject = OFB_Tags::replace( (string) ( $receipt['subject'] ?? '' ), $data, $extra );
		$body    = OFB_Tags::replace( (string) ( $receipt['body'] ?? '' ), $data, $extra );
		self::mail( $to, $subject, $body );
	}

	/**
	 * Resolve the confirmation recipient. Order of precedence:
	 *  1. Conditional routing map (if the routing field's value matches).
	 *  2. Routing default address.
	 *  3. The confirmation "to" template (may be a {tag} or literal address).
	 *  4. Site admin email.
	 */
	private static function resolve_recipient( array $emails, array $conf, array $data ): string {
		$routing = is_array( $emails['routing'] ?? null ) ? $emails['routing'] : [];
		$field   = (string) ( $routing['field'] ?? '' );

		if ( '' !== $field && isset( $data[ $field ] ) ) {
			$value = is_array( $data[ $field ]['value'] ?? null )
				? implode( ',', $data[ $field ]['value'] )
				: (string) ( $data[ $field ]['value'] ?? '' );
			foreach ( ( $routing['map'] ?? [] ) as $rule ) {
				if ( isset( $rule['value'] ) && (string) $rule['value'] === $value && is_email( $rule['email'] ) ) {
					return $rule['email'];
				}
			}
		}

		if ( ! empty( $routing['default'] ) && is_email( $routing['default'] ) ) {
			return $routing['default'];
		}

		$tpl = sanitize_email( OFB_Tags::replace( (string) ( $conf['to'] ?? '' ), $data ) );
		if ( $tpl && is_email( $tpl ) ) {
			return $tpl;
		}

		return get_option( 'admin_email' );
	}

	private static function first_email_value( array $data ): string {
		foreach ( $data as $entry ) {
			$value = is_array( $entry ) ? ( $entry['value'] ?? '' ) : $entry;
			if ( is_string( $value ) && is_email( $value ) ) {
				return $value;
			}
		}
		return '';
	}

	private static function mail( string $to, string $subject, string $body ): void {
		if ( '' === trim( $subject ) ) {
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Form submission', 'open-form-builder' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			);
		}
		// Send as HTML so admin-entered body markup renders; wp_mail + SMTP plugin
		// handles delivery. nl2br keeps plain-text line breaks readable.
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		wp_mail( $to, $subject, wpautop( $body ), $headers );
	}
}
