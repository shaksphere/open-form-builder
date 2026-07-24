<?php
defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the custom database tables via dbDelta.
 *
 * Schema version is stored in an option so we can run dbDelta again on upgrade
 * without forcing a deactivate/reactivate.
 */
class OFB_Activator {

	const SCHEMA_OPTION  = 'ofb_db_version';
	const SCHEMA_VERSION = '3';

	/** Run on activation. */
	public static function install(): void {
		self::create_tables();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	/** Run on every load; no-op once the stored version matches. */
	public static function maybe_upgrade(): void {
		if ( get_option( self::SCHEMA_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::create_tables();
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	private static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$forms       = OFB_DB::forms();
		$submissions = OFB_DB::submissions();
		$slots       = OFB_DB::slots();
		$bookings    = OFB_DB::bookings();

		// Forms: declarative JSON schema + per-form settings JSON.
		dbDelta( "CREATE TABLE {$forms} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			form_schema LONGTEXT NOT NULL,
			settings LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'publish',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id)
		) {$charset};" );

		// Submissions: data JSON + payment lifecycle + Stripe reference.
		dbDelta( "CREATE TABLE {$submissions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			data LONGTEXT NOT NULL,
			amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT '',
			payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			stripe_ref VARCHAR(191) NOT NULL DEFAULT '',
			flagged TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY payment_status (payment_status),
			KEY stripe_ref (stripe_ref)
		) {$charset};" );

		// Slots: capacity & live booked_count per session option, per form.
		// slot_key is the stable identifier the schema/front-end references.
		dbDelta( "CREATE TABLE {$slots} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			slot_key VARCHAR(64) NOT NULL DEFAULT '',
			tab VARCHAR(64) NOT NULL DEFAULT '',
			label VARCHAR(191) NOT NULL DEFAULT '',
			teacher VARCHAR(191) NOT NULL DEFAULT '',
			day VARCHAR(10) NOT NULL DEFAULT '',
			start_min INT NOT NULL DEFAULT 0,
			time_label VARCHAR(64) NOT NULL DEFAULT '',
			exceptions TEXT NULL,
			capacity INT UNSIGNED NOT NULL DEFAULT 0,
			booked_count INT UNSIGNED NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY form_slot (form_id, slot_key),
			KEY form_id (form_id)
		) {$charset};" );

		// Bookings: which submission consumed which slot (depletion audit trail).
		dbDelta( "CREATE TABLE {$bookings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			slot_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY submission_slot (submission_id, slot_id),
			KEY submission_id (submission_id),
			KEY slot_id (slot_id)
		) {$charset};" );
	}
}
