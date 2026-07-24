<?php
defined( 'ABSPATH' ) || exit;

/**
 * The [open_form id="..."] shortcode. Droppable on any page/post. Renders the
 * form and enqueues the front-end assets only when a form is actually output.
 */
class OFB_Shortcode {

	public function register_hooks(): void {
		add_shortcode( 'open_form', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	public function register_assets(): void {
		wp_register_style( 'ofb-frontend', OFB_URL . 'assets/frontend.css', [], OFB_VERSION );
		wp_register_script( 'ofb-frontend', OFB_URL . 'assets/frontend.js', [], OFB_VERSION, true );
		wp_localize_script( 'ofb-frontend', 'OFB_DATA', [
			'restUrl'   => esc_url_raw( rest_url( 'ofb/v1/' ) ),
			// REST cookie nonce: without this, a logged-in visitor's submit runs
			// as logged-out in the REST context and the per-form nonce mismatches.
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'    => [
				'required'   => __( 'This field is required.', 'open-form-builder' ),
				'invalidEmail' => __( 'Please enter a valid email address.', 'open-form-builder' ),
				'submitting' => __( 'Submitting…', 'open-form-builder' ),
				'error'      => __( 'Something went wrong. Please try again.', 'open-form-builder' ),
				'selectMin'  => __( 'Please select at least %d sessions.', 'open-form-builder' ),
				'selectMax'  => __( 'Please select no more than %d sessions.', 'open-form-builder' ),
			],
		] );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'open_form' );
		$id   = (int) $atts['id'];
		if ( $id <= 0 ) {
			return '';
		}

		$form = OFB_Forms::get( $id );
		if ( ! $form || 'publish' !== $form['status'] ) {
			return current_user_can( 'manage_options' )
				? '<p class="ofb-notice">' . esc_html__( 'Open Form Builder: form not found or unpublished.', 'open-form-builder' ) . '</p>'
				: '';
		}

		wp_enqueue_style( 'ofb-frontend' );
		wp_enqueue_script( 'ofb-frontend' );

		// Per-form custom JS (only stored for capable users; printed inline once).
		$custom_js = (string) ( $form['settings']['custom_js'] ?? '' );
		$html = OFB_Renderer::render( $form );
		if ( '' !== trim( $custom_js ) ) {
			$html .= "\n<script>/* ofb custom js: form {$id} */\n" . $custom_js . "\n</script>";
		}

		return $html;
	}
}
