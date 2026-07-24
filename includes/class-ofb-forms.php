<?php
defined( 'ABSPATH' ) || exit;

/**
 * CRUD for the ofb_forms table. A "form" is a name + a JSON schema + a JSON
 * settings blob. Schema is normalized by OFB_Schema and settings by
 * OFB_Security before anything is written.
 */
class OFB_Forms {

	/** @return array{id:int,name:string,schema:array,settings:array,status:string}|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = OFB_DB::forms();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/** @return array<int,array> list of forms (lightweight: id, name, status, dates) */
	public static function all(): array {
		global $wpdb;
		$table = OFB_DB::forms();
		$rows  = $wpdb->get_results( "SELECT id, name, status, created_at, updated_at FROM {$table} ORDER BY updated_at DESC", ARRAY_A );
		return array_map( function ( $r ) {
			return [
				'id'         => (int) $r['id'],
				'name'       => (string) $r['name'],
				'status'     => (string) $r['status'],
				'created_at' => (string) $r['created_at'],
				'updated_at' => (string) $r['updated_at'],
			];
		}, is_array( $rows ) ? $rows : [] );
	}

	/**
	 * Create a form. $schema and $settings must already be normalized/sanitized.
	 * @return int New form id, or 0 on failure.
	 */
	public static function create( string $name, array $schema, array $settings ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$ok = $wpdb->insert(
			OFB_DB::forms(),
			[
				'name'        => $name,
				'form_schema' => wp_json_encode( $schema ),
				'settings'    => wp_json_encode( $settings ),
				'status'      => 'publish',
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/** Update a form. $schema/$settings already normalized/sanitized. */
	public static function update( int $id, string $name, array $schema, array $settings ): bool {
		global $wpdb;
		$ok = $wpdb->update(
			OFB_DB::forms(),
			[
				'name'        => $name,
				'form_schema' => wp_json_encode( $schema ),
				'settings'    => wp_json_encode( $settings ),
				'updated_at'  => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
		return false !== $ok;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		// Slots belong to the form; bookings/submissions are kept for the record.
		$wpdb->delete( OFB_DB::slots(), [ 'form_id' => $id ], [ '%d' ] );
		return (bool) $wpdb->delete( OFB_DB::forms(), [ 'id' => $id ], [ '%d' ] );
	}

	private static function hydrate( array $row ): array {
		$schema   = json_decode( (string) $row['form_schema'], true );
		$settings = json_decode( (string) $row['settings'], true );
		return [
			'id'         => (int) $row['id'],
			'name'       => (string) $row['name'],
			'schema'     => OFB_Schema::normalize( is_array( $schema ) ? $schema : [] ),
			'settings'   => is_array( $settings ) ? $settings : [],
			'status'     => (string) $row['status'],
			'created_at' => (string) $row['created_at'],
			'updated_at' => (string) $row['updated_at'],
		];
	}
}
