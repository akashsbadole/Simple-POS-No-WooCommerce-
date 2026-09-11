<?php
/**
 * Shared database helpers used by every data class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_DB {

	/**
	 * Maximum items per page for paginated queries.
	 */
	const MAX_PER_PAGE = 200;

	/**
	 * Get a fully-qualified custom table name.
	 *
	 * @param string $name Short table name, e.g. 'products'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		// Sanitize prefix to prevent SQL injection if constant is ever filtered.
		$prefix = preg_replace( '/[^a-z0-9_]/i', '', SIMPLE_POS_TABLE_PREFIX );
		return $wpdb->prefix . $prefix . $name;
	}

	/**
	 * Generate the next sequential sale number, e.g. POS-000123.
	 * Uses a dedicated sequence table with AUTO_INCREMENT for atomic operation.
	 *
	 * @return string
	 */
	public static function next_sale_number() {
		global $wpdb;
		$settings = Simple_POS_Settings::get_all();
		$prefix   = isset( $settings['sale_number_prefix'] ) ? $settings['sale_number_prefix'] : 'POS-';

		$seq_table = self::table( 'sale_sequences' );

		// Insert and get AUTO_INCREMENT ID (atomic operation - no race condition).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->query( "INSERT INTO {$seq_table} VALUES ()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $ok ) {
			// Self-heal: sequences table missing on installs that upgraded
			// without a DB version bump. Create it and retry once. Guarded
			// on upgrade.php so CLI/test contexts fall through to the
			// MAX(id) fallback instead of fataling on a missing require.
			if ( class_exists( 'Simple_POS_Activator' ) && method_exists( 'Simple_POS_Activator', 'create_tables' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				Simple_POS_Activator::create_tables();
				$ok = $wpdb->query( "INSERT INTO {$seq_table} VALUES ()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		if ( false === $ok ) {
			// Last resort: derive from max sale id so checkout still works.
			$sales_table = self::table( 'sales' );
			$max_id      = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$sales_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $prefix . str_pad( $max_id + 1, 6, '0', STR_PAD_LEFT );
		}
		$next_id = (int) $wpdb->insert_id;

		// Guard against a sequence that fell behind existing sale_numbers
		// (CSV imports, manual rows and restored backups write to sales
		// without bumping the sequence, and the sequence insert above is
		// rolled back together with a failed sale). Burn sequence ids
		// until the candidate number is actually free, so the sales
		// INSERT below cannot fail its UNIQUE(sale_number) key.
		$sales_table = self::table( 'sales' );
		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$candidate = $prefix . str_pad( $next_id, 6, '0', STR_PAD_LEFT );
			$exists    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sales_table} WHERE sale_number = %s", $candidate ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 0 === $exists ) {
				break;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$retry = $wpdb->query( "INSERT INTO {$seq_table} VALUES ()" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $retry ) {
				break;
			}
			$next_id = (int) $wpdb->insert_id;
		}

		// Optionally clean up old sequence entries (every 1000 inserts).
		if ( $next_id % 1000 === 0 && $next_id > 100 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$seq_table} WHERE id < " . ( $next_id - 100 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// Check if the sales table supports transactions (InnoDB).
		$sample_table = self::table( 'sales' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$engine = $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$sample_table}'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 'InnoDB' !== $engine ) {
			// Table does not support transactions — run callback directly.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PWP.DevelopmentFunctions.error_log_error_log
				error_log( 'POS: Table engine is ' . ( $engine ?: 'unknown' ) . ', running transaction callback without transaction wrapping.' );
			}
			return call_user_func( $callback );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$result = call_user_func( $callback );

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PWP.DevelopmentFunctions.error_log_error_log
				error_log( 'POS Transaction failed: ' . $result->get_error_message() );
				$error_data = $result->get_error_data();
				if ( $error_data ) {
					// phpcs:ignore WordPress.PWP.DevelopmentFunctions.error_log_error_log
					error_log( 'POS Error context: ' . wp_json_encode( $error_data ) );
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'COMMIT' );
		}

		return $result;
	}
}
