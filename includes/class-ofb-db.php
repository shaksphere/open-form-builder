<?php
defined( 'ABSPATH' ) || exit;

/**
 * Central place for table names. All custom tables share the `ofb_` prefix
 * after WordPress's own table prefix.
 */
class OFB_DB {

	public static function forms(): string {
		global $wpdb;
		return $wpdb->prefix . 'ofb_forms';
	}

	public static function submissions(): string {
		global $wpdb;
		return $wpdb->prefix . 'ofb_submissions';
	}

	public static function slots(): string {
		global $wpdb;
		return $wpdb->prefix . 'ofb_slots';
	}

	public static function bookings(): string {
		global $wpdb;
		return $wpdb->prefix . 'ofb_bookings';
	}
}
