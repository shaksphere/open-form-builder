<?php
defined( 'ABSPATH' ) || exit;

/**
 * Optional email-marketing sync. On a completed submission, subscribes the
 * submitter to a Mailchimp audience or a MailerLite group. Fire-and-forget: a
 * marketing hiccup must never block or fail the submission.
 *
 * Provider API keys are site-wide (kept in the Stripe settings option, so secrets
 * never live inside exportable per-form JSON — same rule as the Stripe keys). The
 * per-form settings choose the provider, the audience/group id and which form
 * fields map to the subscriber's email + name.
 */
class OFB_Marketing {

	/**
	 * @param array $settings Form settings (marketing block).
	 * @param array $data     Submission data: name => [label, value].
	 * @param array $extra    Submission context (unused; kept for signature parity).
	 */
	public static function sync( array $settings, array $data, array $extra = [] ): void {
		$m = is_array( $settings['marketing'] ?? null ) ? $settings['marketing'] : [];
		if ( empty( $m['enabled'] ) || '' === trim( (string) ( $m['list_id'] ?? '' ) ) ) {
			return;
		}

		$email = self::field_value( $data, (string) ( $m['email_field'] ?? '' ) );
		if ( '' === $email ) {
			$email = self::first_email_value( $data );
		}
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}

		$name = self::field_value( $data, (string) ( $m['name_field'] ?? '' ) );
		$tags = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $m['tags'] ?? '' ) ) ) ) );

		if ( 'mailerlite' === ( $m['provider'] ?? 'mailchimp' ) ) {
			self::mailerlite( (string) $m['list_id'], $email, $name, $tags );
		} else {
			self::mailchimp( (string) $m['list_id'], $email, $name, $tags, ! empty( $m['double_optin'] ) );
		}
	}

	/** Upsert a member into a Mailchimp audience (idempotent via the email hash). */
	private static function mailchimp( string $list_id, string $email, string $name, array $tags, bool $double_optin ): void {
		$api_key = self::api_key( 'mailchimp_api_key' );
		// Mailchimp keys look like "xxxxxxxx-usXX"; the datacenter is the suffix.
		if ( '' === $api_key || false === strpos( $api_key, '-' ) ) {
			return;
		}
		$dc   = substr( $api_key, strrpos( $api_key, '-' ) + 1 );
		$hash = md5( strtolower( $email ) );
		$url  = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . rawurlencode( $list_id ) . '/members/' . $hash;

		$body = [
			'email_address' => $email,
			'status_if_new' => $double_optin ? 'pending' : 'subscribed',
		];
		if ( '' !== $name ) {
			$parts = explode( ' ', $name, 2 );
			$body['merge_fields'] = [ 'FNAME' => $parts[0] ];
			if ( isset( $parts[1] ) ) {
				$body['merge_fields']['LNAME'] = $parts[1];
			}
		}
		if ( $tags ) {
			$body['tags'] = $tags;
		}

		self::request( $url, 'PUT', $body, [
			'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
		] );
	}

	/** Upsert a subscriber into a MailerLite group (new connect.mailerlite.com API). */
	private static function mailerlite( string $group_id, string $email, string $name, array $tags ): void {
		$api_key = self::api_key( 'mailerlite_api_key' );
		if ( '' === $api_key ) {
			return;
		}
		$body = [ 'email' => $email ];
		if ( '' !== $name ) {
			$body['fields'] = [ 'name' => $name ];
		}
		if ( '' !== $group_id ) {
			$body['groups'] = [ $group_id ];
		}
		self::request( 'https://connect.mailerlite.com/api/subscribers', 'POST', $body, [
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
		] );
	}

	private static function request( string $url, string $method, array $body, array $headers ): void {
		$headers['Content-Type'] = 'application/json';
		wp_remote_request( $url, [
			'method'   => $method,
			'timeout'  => 8,
			'blocking' => false, // fire-and-forget; never hold up the submission response
			'headers'  => $headers,
			'body'     => wp_json_encode( $body ),
		] );
	}

	private static function api_key( string $key ): string {
		$o = get_option( OFB_Stripe::OPTION, [] );
		$o = is_array( $o ) ? $o : [];
		return trim( (string) ( $o[ $key ] ?? '' ) );
	}

	private static function field_value( array $data, string $name ): string {
		if ( '' === $name || ! isset( $data[ $name ] ) ) {
			return '';
		}
		$value = is_array( $data[ $name ] ) ? ( $data[ $name ]['value'] ?? '' ) : $data[ $name ];
		return is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value;
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
}
