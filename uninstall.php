<?php
/**
 * Uninstall: remove all Open Form Builder data. Runs only when the plugin is
 * deleted from the Plugins screen.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [
	$wpdb->prefix . 'ofb_bookings',
	$wpdb->prefix . 'ofb_slots',
	$wpdb->prefix . 'ofb_submissions',
	$wpdb->prefix . 'ofb_forms',
];
foreach ( $tables as $table ) {
	// Table names are built from the trusted prefix + literal suffix.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
}

delete_option( 'ofb_db_version' );
delete_option( 'open_form_builder_settings' );
