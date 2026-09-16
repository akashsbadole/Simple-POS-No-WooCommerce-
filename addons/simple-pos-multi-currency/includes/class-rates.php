<?php
/**
 * Exchange-rate registry for multi-currency tender.
 * Rate is how many base-currency units one foreign unit buys.
 *
 * Example: base INR, rate_to_base for USD = 83 means 1 USD = 83 INR.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Sfx_Rates {

	const TABLE_SUFFIX = 'fx_rates';

	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TABLE_SUFFIX . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, currency_code CHAR(3) NOT NULL, rate_to_base DECIMAL(14,6) NOT NULL, updated_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY uniq_code (currency_code) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @return object[] Active rates (code, rate_to_base).
	 */
	public static function get_rates() {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY currency_code ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param string $code
	 * @return float Rate to base, or 0 when unknown.
	 */
	public static function get_rate( $code ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		$rate  = $wpdb->get_var( $wpdb->prepare( "SELECT rate_to_base FROM {$table} WHERE currency_code = %s", strtoupper( $code ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rate ? (float) $rate : 0;
	}

	/**
	 * @param string $code
	 * @param float  $rate
	 */
	public static function upsert_rate( $code, $rate ) {
		global $wpdb;
		self::ensure_schema();
		$code = strtoupper( $code );
		$rate = max( 0.000001, (float) $rate );
		self::delete_rate( $code );
		$wpdb->insert(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array(
				'currency_code' => $code,
				'rate_to_base'  => $rate,
				'updated_at'    => current_time( 'mysql' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}

	/**
	 * @param string $code
	 */
	public static function delete_rate( $code ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->delete( Simple_POS_DB::table( self::TABLE_SUFFIX ), array( 'currency_code' => strtoupper( $code ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}
}