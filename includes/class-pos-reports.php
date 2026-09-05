<?php
/**
 * Reporting queries. Aggregate queries are cached briefly with transients
 * since a busy terminal can otherwise trigger the same dashboard query
 * repeatedly within seconds.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Reports {

	const CACHE_TTL = 120; // seconds

	/**
	 * High-level summary for a date range: revenue, sale count, average sale,
	 * items sold. Only counts 'completed' sales.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array
	 */
	public static function get_summary( $date_from, $date_to ) {
		global $wpdb;

		$cache_key = 'simple_pos_summary_' . md5( $date_from . $date_to );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$sales_table = Simple_POS_DB::table( 'sales' );
		$items_table = Simple_POS_DB::table( 'sale_items' );

		$from = gmdate( 'Y-m-d 00:00:00', strtotime( $date_from ) );
		$to   = gmdate( 'Y-m-d 23:59:59', strtotime( $date_to ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS sale_count, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS avg_sale
				 FROM {$sales_table}
				 WHERE status = 'completed' AND created_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to
			)
		);

		$items_sold = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(si.qty),0)
				 FROM {$items_table} si
				 INNER JOIN {$sales_table} s ON s.id = si.sale_id
				 WHERE s.status = 'completed' AND s.created_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to
			)
		);

		$profit = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM((si.price - si.cost_price) * si.qty),0)
				 FROM {$items_table} si
				 INNER JOIN {$sales_table} s ON s.id = si.sale_id
				 WHERE s.status = 'completed' AND s.created_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to
			)
		);

		$result = array(
			'sale_count'  => (int) $row->sale_count,
			'revenue'     => round( (float) $row->revenue, 2 ),
			'avg_sale'    => round( (float) $row->avg_sale, 2 ),
			'items_sold'  => $items_sold,
			'gross_profit' => round( $profit, 2 ),
		);

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Revenue and sale count grouped by day, for a simple trend chart.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array List of { date, revenue, sale_count }.
	 */
	public static function get_sales_by_day( $date_from, $date_to ) {
		global $wpdb;

		$cache_key = 'simple_pos_byday_' . md5( $date_from . $date_to );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$sales_table = Simple_POS_DB::table( 'sales' );
		$from        = gmdate( 'Y-m-d 00:00:00', strtotime( $date_from ) );
		$to          = gmdate( 'Y-m-d 23:59:59', strtotime( $date_to ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS revenue, COUNT(*) AS sale_count
				 FROM {$sales_table}
				 WHERE status = 'completed' AND created_at BETWEEN %s AND %s
				 GROUP BY DATE(created_at)
				 ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to
			),
			ARRAY_A
		);

		set_transient( $cache_key, $rows, self::CACHE_TTL );

		return $rows;
	}

	/**
	 * Best-selling products by quantity within a date range.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @param int    $limit     Max rows.
	 * @return array
	 */
	public static function get_top_products( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		$cache_key = 'simple_pos_top_' . md5( $date_from . $date_to . $limit );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$sales_table = Simple_POS_DB::table( 'sales' );
		$items_table = Simple_POS_DB::table( 'sale_items' );
		$from        = gmdate( 'Y-m-d 00:00:00', strtotime( $date_from ) );
		$to          = gmdate( 'Y-m-d 23:59:59', strtotime( $date_to ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT si.product_id, si.product_name, SUM(si.qty) AS qty_sold, SUM(si.line_total) AS revenue
				 FROM {$items_table} si
				 INNER JOIN {$sales_table} s ON s.id = si.sale_id
				 WHERE s.status = 'completed' AND s.created_at BETWEEN %s AND %s
				 GROUP BY si.product_id, si.product_name
				 ORDER BY qty_sold DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to,
				$limit
			)
		);

		set_transient( $cache_key, $rows, self::CACHE_TTL );

		return $rows;
	}

	/**
	 * Sales broken down by cashier — useful for shift/staff performance.
	 *
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array
	 */
	public static function get_sales_by_cashier( $date_from, $date_to ) {
		global $wpdb;
		$sales_table = Simple_POS_DB::table( 'sales' );
		$from        = gmdate( 'Y-m-d 00:00:00', strtotime( $date_from ) );
		$to          = gmdate( 'Y-m-d 23:59:59', strtotime( $date_to ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cashier_id, COUNT(*) AS sale_count, COALESCE(SUM(total),0) AS revenue
				 FROM {$sales_table}
				 WHERE status = 'completed' AND created_at BETWEEN %s AND %s
				 GROUP BY cashier_id
				 ORDER BY revenue DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to
			)
		);

		foreach ( $rows as $row ) {
			$user         = get_userdata( $row->cashier_id );
			$row->cashier_name = $user ? $user->display_name : __( 'Unknown', 'simple-pos' );
		}

		return $rows;
	}

	/**
	 * Clear all cached report transients. Called whenever a sale is
	 * created/voided so reports reflect it promptly rather than waiting
	 * out the TTL.
	 */
	public static function flush_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_simple_pos_%' OR option_name LIKE '_transient_timeout_simple_pos_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
