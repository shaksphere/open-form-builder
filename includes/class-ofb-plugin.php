<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main bootstrap. Loads modules and wires up WordPress hooks. One instance,
 * created on `plugins_loaded`.
 */
final class OFB_Plugin {

	/** @var OFB_Plugin|null */
	private static $instance = null;

	/** @var OFB_REST */
	public $rest;

	/** @var OFB_Stripe */
	public $stripe;

	public static function instance(): OFB_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_files();
	}

	private function load_files(): void {
		$inc = OFB_DIR . 'includes/';
		require_once $inc . 'class-ofb-db.php';
		require_once $inc . 'class-ofb-activator.php';
		require_once $inc . 'class-ofb-security.php';
		require_once $inc . 'class-ofb-schema.php';
		require_once $inc . 'class-ofb-forms.php';
		require_once $inc . 'class-ofb-pricing.php';
		require_once $inc . 'class-ofb-slots.php';
		require_once $inc . 'class-ofb-tags.php';
		require_once $inc . 'class-ofb-emails.php';
		require_once $inc . 'class-ofb-marketing.php';
		require_once $inc . 'class-ofb-sheets.php';
		require_once $inc . 'class-ofb-submissions.php';
		require_once $inc . 'class-ofb-renderer.php';
		require_once $inc . 'class-ofb-shortcode.php';
		require_once $inc . 'class-ofb-stripe.php';
		require_once $inc . 'class-ofb-rest.php';
		require_once $inc . 'class-ofb-cf7-import.php';
		require_once $inc . 'class-ofb-admin.php';
	}

	public function boot(): void {
		load_plugin_textdomain( 'open-form-builder', false, dirname( plugin_basename( OFB_FILE ) ) . '/languages' );

		// Self-healing: create tables if a new install missed the activation hook
		// (e.g. dropped in via mu or symlink). Cheap option check, runs once.
		OFB_Activator::maybe_upgrade();

		( new OFB_Shortcode() )->register_hooks();

		$this->stripe = new OFB_Stripe();
		$this->stripe->register_hooks();

		$this->rest = new OFB_REST( $this->stripe );
		$this->rest->register_hooks();

		if ( is_admin() ) {
			( new OFB_Admin() )->register_hooks();
		}

		do_action( 'open_form_builder_loaded', $this );
	}

	/** Activation: create all custom tables and seed default options. */
	public static function activate(): void {
		require_once OFB_DIR . 'includes/class-ofb-db.php';
		require_once OFB_DIR . 'includes/class-ofb-activator.php';
		OFB_Activator::install();
	}

	public static function deactivate(): void {
		// Intentionally non-destructive. Data is removed only via uninstall.php.
	}
}
