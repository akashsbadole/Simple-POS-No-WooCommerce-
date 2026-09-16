<?php
/**
 * Table management for restaurant POS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Sts_Tables {

	const TABLE_SUFFIX = 'tables';

	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TABLE_SUFFIX . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(191) NOT NULL, seats INT UNSIGNED NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'available', active_sale_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function get_tables() {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get tables grouped by status for the floor view.
	 *
	 * @return array
	 */
	public static function get_by_status() {
		$grouped = array(
			'available' => array(),
			'occupied'  => array(),
			'reserved'  => array(),
		);
		foreach ( self::get_tables() as $t ) {
			$key = isset( $grouped[ $t->status ] ) ? $t->status : 'available';
			$grouped[ $key ][] = $t;
		}
		return $grouped;
	}

	/**
	 * @param int $id
	 * @return object|null
	 */
	public static function get_table( $id ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function create_table( $name, $seats = 1 ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->insert(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array(
				'name'       => sanitize_text_field( $name ),
				'seats'      => max( 1, (int) $seats ),
				'status'     => 'available',
				'created_at' => current_time( 'mysql' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->insert_id;
	}

	public static function update_table( $id, $name, $seats = 1 ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->update(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array(
				'name'  => sanitize_text_field( $name ),
				'seats' => max( 1, (int) $seats ),
			),
			array( 'id' => (int) $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}

	/**
	 * Set table status and optionally link to an active sale.
	 *
	 * @param int    $id
	 * @param string $status One of: available, occupied, reserved.
	 * @param int    $sale_id Optional active sale ID.
	 */
	public static function set_status( $id, $status, $sale_id = 0 ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->update(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array(
				'status'        => sanitize_text_field( $status ),
				'active_sale_id' => $sale_id > 0 ? $sale_id : null,
			),
			array( 'id' => (int) $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}

	/**
	 * Mark a table as occupied when a sale is assigned to it.
	 *
	 * @param int $table_id
	 * @param int $sale_id
	 */
	public static function occupy( $table_id, $sale_id ) {
		if ( $table_id > 0 && $sale_id > 0 ) {
			self::set_status( $table_id, 'occupied', $sale_id );
		}
	}

	/**
	 * Release a table back to available.
	 *
	 * @param int $table_id
	 */
	public static function release( $table_id ) {
		if ( $table_id > 0 ) {
			self::set_status( $table_id, 'available', 0 );
		}
	}

	public static function delete_table( $id ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->delete( Simple_POS_DB::table( self::TABLE_SUFFIX ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}
}
