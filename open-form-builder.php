<?php
/**
 * Plugin Name:       Open Form Builder
 * Plugin URI:        https://example.com/open-form-builder
 * Description:       A CF7-style form builder with 5 starter templates, multi-step wizards, conditional logic, image-card choice fields, a capacity-aware session picker, two pricing models (sessions or priced options/quantities), per-form branding, Stripe Checkout, date/time fields, conditional emails, Mailchimp/MailerLite sync, Google Sheet export and CF7 import. Forms are declarative JSON schemas.
 * Version:           0.4.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Open Form Builder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       open-form-builder
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'OFB_VERSION', '0.4.0' );
define( 'OFB_FILE', __FILE__ );
define( 'OFB_DIR', plugin_dir_path( __FILE__ ) );
define( 'OFB_URL', plugin_dir_url( __FILE__ ) );
define( 'OFB_MIN_PHP', '8.0' );

// Hard stop on unsupported PHP rather than fataling on modern syntax.
if ( version_compare( PHP_VERSION, OFB_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__( 'Open Form Builder requires PHP %1$s or higher. You are running %2$s.', 'open-form-builder' ),
				OFB_MIN_PHP,
				PHP_VERSION
			) )
		);
	} );
	return;
}

require_once OFB_DIR . 'includes/class-ofb-plugin.php';

register_activation_hook( __FILE__, [ 'OFB_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'OFB_Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	OFB_Plugin::instance()->boot();
} );
