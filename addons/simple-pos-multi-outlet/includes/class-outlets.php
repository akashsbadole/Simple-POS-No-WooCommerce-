<?php
/**
 * Outlet registry: desks/counters/shops sharing one install.
 * Sales are tagged with outlet_id at checkout; stock stays global.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMO_Outlets {

	const OPTION_KEY = 'simple_pos_outlets';

	/**
	 * Create the outlets table if missing (guarded per request).
	 */
	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}outlets` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(191) NOT NULL, address TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Add-on settings (just the default outlet for now).
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'default_outlet_id' => isset( $saved['default_outlet_id'] ) ? (int) $saved['default_outlet_id'] : 0,
		);
	}

	/**
	 * @return int
	 */
	public static function get_default_outlet_id() {
		$settings = self::get_settings();
		return (int) $settings['default_outlet_id'];
	}

	/**
	 * @param int $id
	 */
	public static function set_default_outlet_id( $id ) {
		update_option( self::OPTION_KEY, array( 'default_outlet_id' => max( 0, (int) $id ) ) );
	}

	/**
	 * @return object[] All outlets, name order.
	 */
	public static function get_outlets() {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( 'outlets' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param int $id
	 * @return object|null
	 */
	public static function get_outlet( $id ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( 'outlets' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param string $name
	 * @param string $address
	 * @return int New outlet ID.
	 */
	public static function create_outlet( $name, $address = '' ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->insert(
			Simple_POS_DB::table( 'outlets' ),
			array(
				'name'       => sanitize_text_field( $name ),
				'address'    => sanitize_textarea_field( $address ),
				'created_at' => current_time( 'mysql' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int    $id
	 * @param string $name
	 * @param string $address
	 */
	public static function update_outlet( $id, $name, $address = '' ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->update(
			Simple_POS_DB::table( 'outlets' ),
			array(
				'name'    => sanitize_text_field( $name ),
				'address' => sanitize_textarea_field( $address ),
			),
			array( 'id' => (int) $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}

	/**
	 * Delete an outlet. Past sales keep outlet_id (nullable history).
	 *
	 * @param int $id
	 */
	public static function delete_outlet( $id ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->delete( Simple_POS_DB::table( 'outlets' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( self::get_default_outlet_id() === (int) $id ) {
			self::set_default_outlet_id( 0 );
		}
		return true;
	}
}
