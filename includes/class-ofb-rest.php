<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API: the public submit + Stripe webhook endpoints, plus the authenticated
 * builder CRUD endpoints used by the React admin app.
 *
 * Namespace: ofb/v1
 */
class OFB_REST {

	const NS = 'ofb/v1';

	/** @var OFB_Stripe */
	private $stripe;

	public function __construct( OFB_Stripe $stripe ) {
		$this->stripe = $stripe;
	}

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// ---- Public ----
		register_rest_route( self::NS, '/submit', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit' ],
			'permission_callback' => '__return_true', // nonce checked inside
		] );

		register_rest_route( self::NS, '/webhook', [
			'methods'             => 'POST',
			'callback'            => [ $this->stripe, 'handle_webhook' ],
			'permission_callback' => '__return_true', // signature verified inside
		] );

		// ---- Admin (builder) ----
		$admin = [ 'permission_callback' => [ $this, 'can_manage' ] ];

		register_rest_route( self::NS, '/forms', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'list_forms' ] ] + $admin,
			[ 'methods' => 'POST', 'callback' => [ $this, 'create_form' ] ] + $admin,
		] );

		register_rest_route( self::NS, '/forms/(?P<id>\d+)', [
			[ 'methods' => 'GET',    'callback' => [ $this, 'get_form' ] ] + $admin,
			[ 'methods' => 'POST',   'callback' => [ $this, 'update_form' ] ] + $admin,
			[ 'methods' => 'DELETE', 'callback' => [ $this, 'delete_form' ] ] + $admin,
		] );

		register_rest_route( self::NS, '/forms/(?P<id>\d+)/submissions', [
			[ 'methods' => 'GET', 'callback' => [ $this, 'list_submissions' ] ] + $admin,
		] );

		register_rest_route( self::NS, '/import-cf7', [
			[ 'methods' => 'POST', 'callback' => [ $this, 'import_cf7' ] ] + $admin,
		] );
	}

	public function can_manage(): bool {
		return OFB_Security::can_manage();
	}

	// ---------------------------------------------------------------- Public

	public function submit( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'ofb_nonce' );
		if ( ! wp_verify_nonce( (string) $nonce, OFB_Security::NONCE_SUBMIT ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => __( 'Your session expired. Please reload and try again.', 'open-form-builder' ) ], 403 );
		}

		$form_id = (int) $request->get_param( 'form_id' );
		$form    = $form_id ? OFB_Forms::get( $form_id ) : null;
		if ( ! $form || 'publish' !== $form['status'] ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => __( 'Form not found.', 'open-form-builder' ) ], 404 );
		}

		$fields = $request->get_param( 'fields' );
		$fields = is_array( $fields ) ? $fields : [];

		$result = OFB_Submissions::validate( $form, $fields );
		if ( ! $result['ok'] ) {
			return new WP_REST_Response( [ 'ok' => false, 'errors' => $result['errors'], 'message' => __( 'Please fix the highlighted fields.', 'open-form-builder' ) ], 422 );
		}

		$payments    = is_array( $form['settings']['payments'] ?? null ) ? $form['settings']['payments'] : [];
		$pay_enabled = ! empty( $payments['enabled'] ) && $result['amount'] > 0;
		$currency    = $payments['currency'] ?? 'aud';

		$submission_id = OFB_Submissions::create_pending( $form_id, $result['data'], $result['session_keys'], $result['amount'], $currency );
		if ( ! $submission_id ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => __( 'Could not save your submission. Please try again.', 'open-form-builder' ) ], 500 );
		}

		// Payment path: hand back a Stripe Checkout URL to redirect to.
		if ( $pay_enabled ) {
			$submission = OFB_Submissions::get( $submission_id );
			$checkout   = $this->stripe->create_checkout_session( $submission, $form );
			if ( ! $checkout['ok'] ) {
				return new WP_REST_Response( [ 'ok' => false, 'message' => $checkout['error'] ], 502 );
			}
			return new WP_REST_Response( [ 'ok' => true, 'redirect' => $checkout['url'] ], 200 );
		}

		// No-payment path: finalize now (emails, sheet, slot depletion).
		OFB_Submissions::finalize( $submission_id, OFB_Submissions::STATUS_FREE );

		$thank_you = (string) ( $form['settings']['redirects']['thank_you_url'] ?? '' );
		$message   = (string) ( $form['settings']['messages']['success'] ?? '' );
		return new WP_REST_Response( [
			'ok'       => true,
			'redirect' => $thank_you ? add_query_arg( [ 'ofb_submission' => $submission_id ], $thank_you ) : '',
			'message'  => $message ?: __( 'Thank you. Your submission was received.', 'open-form-builder' ),
		], 200 );
	}

	// ----------------------------------------------------------------- Admin

	public function list_forms(): WP_REST_Response {
		return new WP_REST_Response( OFB_Forms::all(), 200 );
	}

	public function get_form( WP_REST_Request $request ): WP_REST_Response {
		$form = OFB_Forms::get( (int) $request['id'] );
		if ( ! $form ) {
			return new WP_REST_Response( [ 'message' => __( 'Not found.', 'open-form-builder' ) ], 404 );
		}
		$form['slots'] = OFB_Slots::for_form( $form['id'] );
		return new WP_REST_Response( $form, 200 );
	}

	public function create_form( WP_REST_Request $request ): WP_REST_Response {
		[ $name, $schema, $settings, $slots ] = $this->read_form_payload( $request );
		$id = OFB_Forms::create( $name, $schema, $settings );
		if ( ! $id ) {
			return new WP_REST_Response( [ 'message' => __( 'Could not create form.', 'open-form-builder' ) ], 500 );
		}
		OFB_Slots::save_for_form( $id, $slots );
		$form = OFB_Forms::get( $id );
		$form['slots'] = OFB_Slots::for_form( $id );
		return new WP_REST_Response( $form, 201 );
	}

	public function update_form( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request['id'];
		if ( ! OFB_Forms::get( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Not found.', 'open-form-builder' ) ], 404 );
		}
		[ $name, $schema, $settings, $slots ] = $this->read_form_payload( $request );
		OFB_Forms::update( $id, $name, $schema, $settings );
		OFB_Slots::save_for_form( $id, $slots );
		$form = OFB_Forms::get( $id );
		$form['slots'] = OFB_Slots::for_form( $id );
		return new WP_REST_Response( $form, 200 );
	}

	public function delete_form( WP_REST_Request $request ): WP_REST_Response {
		OFB_Forms::delete( (int) $request['id'] );
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	public function list_submissions( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( OFB_Submissions::for_form( (int) $request['id'] ), 200 );
	}

	public function import_cf7( WP_REST_Request $request ): WP_REST_Response {
		$source = (string) $request->get_param( 'source' );
		$mail   = (string) $request->get_param( 'mail' );
		if ( '' === trim( $source ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Paste a CF7 form template to import.', 'open-form-builder' ) ], 400 );
		}
		$imported = OFB_CF7_Import::import( $source, $mail );
		return new WP_REST_Response( $imported, 200 );
	}

	/**
	 * Normalize an inbound form payload: name + schema + settings (sanitized) +
	 * raw slot definitions for ofb_slots.
	 *
	 * @return array{0:string,1:array,2:array,3:array}
	 */
	private function read_form_payload( WP_REST_Request $request ): array {
		$name     = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$schema   = OFB_Schema::normalize( $request->get_param( 'schema' ) );
		$allow_js = current_user_can( 'unfiltered_html' );
		$settings = OFB_Security::sanitize_settings( $request->get_param( 'settings' ), $allow_js );
		$slots    = $request->get_param( 'slots' );
		$slots    = is_array( $slots ) ? $slots : [];
		return [ $name ?: __( 'Untitled form', 'open-form-builder' ), $schema, $settings, $slots ];
	}
}
