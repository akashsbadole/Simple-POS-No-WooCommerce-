<?php
/**
 * Shared database helpers used by every data class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_DB {

	/**
	 * Get a fully-qualified custom table name.
	 *
	 * @param string $name Short table name, e.g. 'products'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX . $name;
	}

	/**
	 * Generate the next sequential sale number, e.g. POS-000123.
	 * Uses a dedicated autoincrement-backed counter (the sales table's own
	 * AUTO_INCREMENT id) rather than COUNT(*), so it stays correct even
	 * after sales are voided/deleted.
	 *
	 * @return string
	 */
	public static function next_sale_number() {
		global $wpdb;
		$settings = Simple_POS_Settings::get_all();
		$prefix   = isset( $settings['sale_number_prefix'] ) ? $settings['sale_number_prefix'] : 'POS-';

		$table = self::table( 'sales' );
		// Try information_schema first (may be restricted on some hosts), fallback to MAX(id)+1.
		$next_id = (int) $wpdb->get_var( "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $next_id ) {
			$max_id  = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$next_id = $max_id + 1;
			if ( $next_id < 1 ) {
				$next_id = 1;
			}
		}

		return $prefix . str_pad( $next_id, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Format a raw number as currency using plugin settings.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_currency( $amount ) {
		$settings = Simple_POS_Settings::get_all();
		$symbol   = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';
		$decimals = isset( $settings['decimal_places'] ) ? (int) $settings['decimal_places'] : 2;
		$position = isset( $settings['currency_position'] ) ? $settings['currency_position'] : 'before';
		$number   = number_format( (float) $amount, $decimals );

		return 'after' === $position ? $number . $symbol : $symbol . $number;
	}

	/**
	 * Check if a SKU already exists in products or variants (for validation).
	 *
	 * @param string $sku SKU.
	 * @param int    $exclude_product_id Product ID to exclude (0 = none).
	 * @param int    $exclude_variant_id Variant ID to exclude (0 = none).
	 * @return bool
	 */
	public static function sku_exists( $sku, $exclude_product_id = 0, $exclude_variant_id = 0 ) {
		global $wpdb;
		$sku = sanitize_text_field( $sku );
		if ( '' === $sku ) {
			return false;
		}
		$pt = self::table( 'products' );
		$vt = self::table( 'product_variants' );
		$c1 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pt} WHERE sku = %s AND id != %d", $sku, $exclude_product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $c1 > 0 ) {
			return true;
		}
		$c2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$vt} WHERE sku = %s AND id != %d", $sku, $exclude_variant_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $c2 > 0;
	}

	/**
	 * Check if a barcode already exists.
	 *
	 * @param string $code Barcode.
	 * @param int    $exclude_product_id Product ID to exclude.
	 * @param int    $exclude_variant_id Variant ID to exclude.
	 * @return bool
	 */
	public static function barcode_exists( $code, $exclude_product_id = 0, $exclude_variant_id = 0 ) {
		global $wpdb;
		$code = sanitize_text_field( $code );
		if ( '' === $code ) {
			return false;
		}
		$pt = self::table( 'products' );
		$vt = self::table( 'product_variants' );
		$c1 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pt} WHERE barcode = %s AND id != %d", $code, $exclude_product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $c1 > 0 ) {
			return true;
		}
		$c2 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$vt} WHERE barcode = %s AND id != %d", $code, $exclude_variant_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $c2 > 0;
	}

	/**
	 * Run a callback wrapped in a DB transaction (InnoDB only).
	 * Falls back to running the callback directly if the storage engine
	 * does not support transactions — dbDelta() always creates InnoDB
	 * tables on modern MySQL, but we stay defensive.
	 *
	 * @param callable $callback Callback that returns true on success, WP_Error on failure.
	 * @return mixed Callback's return value.
	 */
	public static function transaction( $callback ) {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		$result = call_user_func( $callback );

		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
		} else {
			$wpdb->query( 'COMMIT' );
		}

		return $result;
	}
}
