<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin UI: the React builder mount point and the site-wide settings page
 * (Stripe keys + webhook secret). Submission viewing is handled inside the
 * React app via the REST endpoints.
 */
class OFB_Admin {

	const MENU_SLUG     = 'open-form-builder';
	const SETTINGS_SLUG = 'open-form-builder-settings';

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Open Form Builder', 'open-form-builder' ),
			__( 'Form Builder', 'open-form-builder' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_builder' ],
			'dashicons-feedback',
			26
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Forms', 'open-form-builder' ),
			__( 'Forms', 'open-form-builder' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_builder' ]
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'open-form-builder' ),
			__( 'Settings', 'open-form-builder' ),
			'manage_options',
			self::SETTINGS_SLUG,
			[ $this, 'render_settings' ]
		);
	}

	// -------------------------------------------------------------- Builder

	public function render_builder(): void {
		echo '<div class="wrap"><div id="ofb-app"></div></div>';
	}

	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}

		$asset_file = OFB_DIR . 'build/index.asset.php';
		$deps    = [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ];
		$version = OFB_VERSION;
		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $asset['dependencies'] ?? $deps;
			$version = $asset['version'] ?? $version;
		}

		// Builder app + WP component styles so it matches Gutenberg.
		if ( file_exists( OFB_DIR . 'build/index.js' ) ) {
			wp_enqueue_script( 'ofb-builder', OFB_URL . 'build/index.js', $deps, $version, true );
		}
		wp_enqueue_style( 'wp-components' );
		if ( file_exists( OFB_DIR . 'build/index.css' ) ) {
			wp_enqueue_style( 'ofb-builder', OFB_URL . 'build/index.css', [ 'wp-components' ], $version );
		}

		wp_localize_script( 'ofb-builder', 'OFB_ADMIN', [
			'restUrl'    => esc_url_raw( rest_url( OFB_REST::NS ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'webhookUrl' => esc_url_raw( rest_url( OFB_REST::NS . '/webhook' ) ),
			'fieldTypes' => OFB_Schema::all_types(),
			'operators'  => OFB_Schema::OPERATORS,
			'settingsUrl'=> admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ),
		] );
		wp_set_script_translations( 'ofb-builder', 'open-form-builder' );
	}

	// ------------------------------------------------------------- Settings

	public function register_settings(): void {
		register_setting( 'ofb_settings_group', OFB_Stripe::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
			'default'           => [],
		] );
	}

	public function sanitize_settings( $in ): array {
		$in = is_array( $in ) ? $in : [];
		return [
			'mode'             => ( ( $in['mode'] ?? 'test' ) === 'live' ) ? 'live' : 'test',
			'test_secret'      => sanitize_text_field( (string) ( $in['test_secret'] ?? '' ) ),
			'test_publishable' => sanitize_text_field( (string) ( $in['test_publishable'] ?? '' ) ),
			'live_secret'      => sanitize_text_field( (string) ( $in['live_secret'] ?? '' ) ),
			'live_publishable' => sanitize_text_field( (string) ( $in['live_publishable'] ?? '' ) ),
			'webhook_secret'   => sanitize_text_field( (string) ( $in['webhook_secret'] ?? '' ) ),
		];
	}

	public function render_settings(): void {
		$s          = OFB_Stripe::get_settings();
		$webhook_url = rest_url( OFB_REST::NS . '/webhook' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Open Form Builder — Settings', 'open-form-builder' ); ?></h1>
			<?php
			// options.php redirects back here with ?settings-updated=true on save.
			if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Your changes have been saved.', 'open-form-builder' ); ?></p>
				</div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'ofb_settings_group' ); ?>
				<h2><?php esc_html_e( 'Stripe', 'open-form-builder' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Mode', 'open-form-builder' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( OFB_Stripe::OPTION ); ?>[mode]">
								<option value="test" <?php selected( $s['mode'], 'test' ); ?>><?php esc_html_e( 'Test', 'open-form-builder' ); ?></option>
								<option value="live" <?php selected( $s['mode'], 'live' ); ?>><?php esc_html_e( 'Live', 'open-form-builder' ); ?></option>
							</select>
						</td>
					</tr>
					<?php
					$this->key_row( __( 'Test secret key', 'open-form-builder' ), 'test_secret', $s['test_secret'] );
					$this->key_row( __( 'Test publishable key', 'open-form-builder' ), 'test_publishable', $s['test_publishable'] );
					$this->key_row( __( 'Live secret key', 'open-form-builder' ), 'live_secret', $s['live_secret'] );
					$this->key_row( __( 'Live publishable key', 'open-form-builder' ), 'live_publishable', $s['live_publishable'] );
					$this->key_row( __( 'Webhook signing secret', 'open-form-builder' ), 'webhook_secret', $s['webhook_secret'] );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook endpoint URL', 'open-form-builder' ); ?></th>
						<td>
							<code><?php echo esc_html( $webhook_url ); ?></code>
							<p class="description"><?php esc_html_e( 'In Stripe → Developers → Webhooks, add this URL and subscribe to the "checkout.session.completed" event. Paste the signing secret above.', 'open-form-builder' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function key_row( string $label, string $key, string $value ): void {
		printf(
			'<tr><th scope="row">%s</th><td><input type="password" class="regular-text" name="%s[%s]" value="%s" autocomplete="off"></td></tr>',
			esc_html( $label ),
			esc_attr( OFB_Stripe::OPTION ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}
}
