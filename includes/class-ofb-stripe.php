<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stripe Checkout integration via the HTTP API directly (no SDK to bundle).
 *
 * - Dynamic Checkout Sessions (not static payment links), one Stripe line item
 *   for the computed total, carrying the submission id in metadata.
 * - A webhook endpoint marks the submission paid, which triggers slot depletion,
 *   emails and sheet export (see OFB_Submissions::finalize).
 *
 * API keys + webhook secret are site-wide (one option), never per-form.
 */
class OFB_Stripe {

	const OPTION   = 'open_form_builder_settings';
	const API_BASE = 'https://api.stripe.com/v1';

	public function register_hooks(): void {
		// REST routes registered by OFB_REST; nothing time-based to hook here.
	}

	public static function get_settings(): array {
		$o = get_option( self::OPTION, [] );
		$o = is_array( $o ) ? $o : [];
		return [
			'mode'            => ( ( $o['mode'] ?? 'test' ) === 'live' ) ? 'live' : 'test',
			'test_secret'     => (string) ( $o['test_secret'] ?? '' ),
			'test_publishable'=> (string) ( $o['test_publishable'] ?? '' ),
			'live_secret'     => (string) ( $o['live_secret'] ?? '' ),
			'live_publishable'=> (string) ( $o['live_publishable'] ?? '' ),
			'webhook_secret'  => (string) ( $o['webhook_secret'] ?? '' ),
			'mailchimp_api_key'  => (string) ( $o['mailchimp_api_key'] ?? '' ),
			'mailerlite_api_key' => (string) ( $o['mailerlite_api_key'] ?? '' ),
		];
	}

	public static function secret_key(): string {
		$s = self::get_settings();
		return 'live' === $s['mode'] ? $s['live_secret'] : $s['test_secret'];
	}

	public static function is_configured(): bool {
		return '' !== self::secret_key();
	}

	/**
	 * Create a Checkout Session for a pending submission.
	 *
	 * @return array{ok:bool,url?:string,id?:string,error?:string}
	 */
	public function create_checkout_session( array $submission, array $form ): array {
		if ( ! self::is_configured() ) {
			return [ 'ok' => false, 'error' => __( 'Payments are not configured.', 'open-form-builder' ) ];
		}

		$settings   = $form['settings'];
		$payments   = is_array( $settings['payments'] ?? null ) ? $settings['payments'] : [];
		$currency   = $submission['currency'] ?: ( $payments['currency'] ?? 'aud' );
		$label      = $payments['product_label'] ?? __( 'Sessions', 'open-form-builder' );
		$amount     = (int) $submission['amount_cents'];

		if ( $amount <= 0 ) {
			return [ 'ok' => false, 'error' => __( 'Nothing to pay.', 'open-form-builder' ) ];
		}

		$thank_you = (string) ( $settings['redirects']['thank_you_url'] ?? '' );
		$success_base = $thank_you ?: home_url( '/' );
		$success_url  = add_query_arg(
			[ 'ofb_submission' => $submission['id'], 'ofb_status' => 'success' ],
			$success_base
		);
		$cancel_url = add_query_arg( [ 'ofb_submission' => $submission['id'], 'ofb_status' => 'cancel' ], wp_get_referer() ?: home_url( '/' ) );

		// Stripe expects form-encoded nested params.
		$body = [
			'mode'                                 => 'payment',
			'success_url'                          => $success_url,
			'cancel_url'                           => $cancel_url,
			'client_reference_id'                  => (string) $submission['id'],
			'metadata[submission_id]'              => (string) $submission['id'],
			'metadata[form_id]'                    => (string) $submission['form_id'],
			'line_items[0][quantity]'              => '1',
			'line_items[0][price_data][currency]'  => $currency,
			'line_items[0][price_data][unit_amount]' => (string) $amount,
			'line_items[0][price_data][product_data][name]' => $label,
		];

		$resp = $this->api_post( '/checkout/sessions', $body );
		if ( ! $resp['ok'] ) {
			return [ 'ok' => false, 'error' => $resp['error'] ];
		}

		$session = $resp['data'];
		// Persist the session id so the webhook can match this submission.
		OFB_Submissions::set_stripe_ref( (int) $submission['id'], (string) ( $session['id'] ?? '' ) );

		return [ 'ok' => true, 'url' => (string) ( $session['url'] ?? '' ), 'id' => (string) ( $session['id'] ?? '' ) ];
	}

	/**
	 * Handle an incoming Stripe webhook. Verifies the signature, then on
	 * checkout.session.completed finalizes the matching submission.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_body();
		$sig     = $request->get_header( 'stripe-signature' );
		$secret  = self::get_settings()['webhook_secret'];

		if ( '' === $secret || ! $this->verify_signature( $payload, (string) $sig, $secret ) ) {
			return new WP_REST_Response( [ 'error' => 'invalid signature' ], 400 );
		}

		$event = json_decode( $payload, true );
		$type  = $event['type'] ?? '';

		if ( 'checkout.session.completed' === $type ) {
			$object        = $event['data']['object'] ?? [];
			$submission_id = (int) ( $object['metadata']['submission_id'] ?? $object['client_reference_id'] ?? 0 );
			$paid          = ( ( $object['payment_status'] ?? '' ) === 'paid' );
			if ( $submission_id > 0 && $paid ) {
				OFB_Submissions::finalize( $submission_id, OFB_Submissions::STATUS_PAID );
			}
		}

		return new WP_REST_Response( [ 'received' => true ], 200 );
	}

	/** Stripe webhook signature scheme: HMAC-SHA256 of "{t}.{payload}". */
	private function verify_signature( string $payload, string $header, string $secret ): bool {
		$parts = [];
		foreach ( explode( ',', $header ) as $piece ) {
			$kv = explode( '=', trim( $piece ), 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ][] = $kv[1];
			}
		}
		$timestamp = $parts['t'][0] ?? '';
		$sigs      = $parts['v1'] ?? [];
		if ( '' === $timestamp || empty( $sigs ) ) {
			return false;
		}
		// Reject events older than 5 minutes (replay protection).
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $sigs as $candidate ) {
			if ( hash_equals( $expected, $candidate ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array{ok:bool,data?:array,error?:string}
	 */
	private function api_post( string $path, array $body ): array {
		$resp = wp_remote_post( self::API_BASE . $path, [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . self::secret_key(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'error' => $resp->get_error_message() ];
		}
		$code = wp_remote_retrieve_response_code( $resp );
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = $data['error']['message'] ?? __( 'Payment provider error.', 'open-form-builder' );
			return [ 'ok' => false, 'error' => $msg ];
		}
		return [ 'ok' => true, 'data' => is_array( $data ) ? $data : [] ];
	}
}
