<?php
/**
 * Advanced Reports queries and CSV builders.
 * Read-only over the core sales/sale_items tables; no schema of its own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPAR_Reports {

	/**
	 * Normalise a Y-m-d range into SQL bounds (same convention as core reports).
	 *
	 * @return array [ from, to ]
	 */
	private static function bounds( $date_from, $date_to ) {
		return array(
			gmdate( 'Y-m-d 00:00:00', strtotime( $date_from ) ),
			gmdate( 'Y-m-d 23:59:59', strtotime( $date_to ) ),
		);
	}

	private static function cashier_sql( $cashier_id ) {
		return $cashier_id ? ' AND cashier_id = %d' : '';
	}

	private static function cashier_params( $from, $to, $cashier_id ) {
		return $cashier_id ? array( $from, $to, (int) $cashier_id ) : array( $from, $to );
	}

	/**
	 * X/Z style totals for a range, optionally per cashier.
	 *
	 * @return array { sale_count, gross, discount, tax, net, avg_basket, items_sold, gross_profit }
	 */
	public static function xz( $date_from, $date_to, $cashier_id = 0 ) {
		global $wpdb;
		list( $from, $to ) = self::bounds( $date_from, $date_to );
		$sales      = Simple_POS_DB::table( 'sales' );
		$items      = Simple_POS_DB::table( 'sale_items' );
		$where_cash = self::cashier_sql( $cashier_id );
		$params     = self::cashier_params( $from, $to, $cashier_id );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS sale_count,
				COALESCE(SUM(subtotal),0) AS gross,
				COALESCE(SUM(discount_amount),0) AS discount,
				COALESCE(SUM(tax_amount),0) AS tax,
				COALESCE(SUM(total),0) AS net,
				COALESCE(AVG(total),0) AS avg_basket
			 FROM {$sales}
			 WHERE status = 'completed' AND created_at BETWEEN %s AND %s{$where_cash}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$params
		) );

		$items_sold = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(si.qty),0)
			 FROM {$items} si INNER JOIN {$sales} s ON s.id = si.sale_id
			 WHERE s.status = 'completed' AND s.created_at BETWEEN %s AND %s{$where_cash}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::cashier_params( $from, $to, $cashier_id )
		) );

		$profit = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM((si.price - si.cost_price) * si.qty),0)
			 FROM {$items} si INNER JOIN {$sales} s ON s.id = si.sale_id
			 WHERE s.status = 'completed' AND s.created_at BETWEEN %s AND %s{$where_cash}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::cashier_params( $from, $to, $cashier_id )
		) );

		return array(
			'sale_count'   => (int) $row->sale_count,
			'gross'        => round( (float) $row->gross, 2 ),
			'discount'     => round( (float) $row->discount, 2 ),
			'tax'          => round( (float) $row->tax, 2 ),
			'net'          => round( (float) $row->net, 2 ),
			'avg_basket'   => round( (float) $row->avg_basket, 2 ),
			'items_sold'   => $items_sold,
			'gross_profit' => round( $profit, 2 ),
		);
	}

	/**
	 * Revenue by hour of day (0-23), zero-filled so charts line up.
	 *
	 * @return array[] List of { hr, sale_count, revenue } for every hour.
	 */
	public static function hourly( $date_from, $date_to ) {
		global $wpdb;
		list( $from, $to ) = self::bounds( $date_from, $date_to );
		$sales = Simple_POS_DB::table( 'sales' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT HOUR(created_at) AS hr, COUNT(*) AS sale_count, COALESCE(SUM(total),0) AS revenue
			 FROM {$sales}
			 WHERE status = 'completed' AND created_at BETWEEN %s AND %s
			 GROUP BY HOUR(created_at)", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$from,
			$to
		) );

		return self::fill_hours( $rows );
	}

	/**
	 * Zero-fill the hourly buckets so every hour 0-23 exists, sorted.
	 *
	 * @param object[]|array[] $rows Rows with hr, sale_count, revenue.
	 * @return array[] Assoc rows keyed 0-23.
	 */
	public static function fill_hours( $rows ) {
		$by_hr = array();
		foreach ( (array) $rows as $r ) {
			$by_hr[ (int) $r->hr ] = array(
				'hr'         => (int) $r->hr,
				'sale_count' => (int) $r->sale_count,
				'revenue'    => round( (float) $r->revenue, 2 ),
			);
		}
		$out = array();
		for ( $h = 0; $h < 24; $h++ ) {
			$out[ $h ] = isset( $by_hr[ $h ] )
				? $by_hr[ $h ]
				: array( 'hr' => $h, 'sale_count' => 0, 'revenue' => 0.0 );
		}
		return $out;
	}

	/**
	 * Revenue grouped by payment method.
	 *
	 * @return object[] { payment_method, sale_count, revenue }
	 */
	public static function payment_mix( $date_from, $date_to, $cashier_id = 0 ) {
		global $wpdb;
		list( $from, $to ) = self::bounds( $date_from, $date_to );
		$sales      = Simple_POS_DB::table( 'sales' );
		$where_cash = self::cashier_sql( $cashier_id );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT payment_method, COUNT(*) AS sale_count, COALESCE(SUM(total),0) AS revenue
			 FROM {$sales}
			 WHERE status = 'completed' AND created_at BETWEEN %s AND %s{$where_cash}
			 GROUP BY payment_method
			 ORDER BY revenue DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::cashier_params( $from, $to, $cashier_id )
		) );
	}

	/**
	 * Best sellers with revenue, profit and margin.
	 *
	 * @return object[] { product_name, sku, qty, revenue, profit, margin }
	 */
	public static function product_mix( $date_from, $date_to, $limit = 20 ) {
		global $wpdb;
		list( $from, $to ) = self::bounds( $date_from, $date_to );
		$sales = Simple_POS_DB::table( 'sales' );
		$items = Simple_POS_DB::table( 'sale_items' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT si.product_name, si.sku,
				SUM(si.qty) AS qty,
				SUM(si.line_total) AS revenue,
				SUM((si.price - si.cost_price) * si.qty) AS profit
			 FROM {$items} si INNER JOIN {$sales} s ON s.id = si.sale_id
			 WHERE s.status = 'completed' AND s.created_at BETWEEN %s AND %s
			 GROUP BY si.product_name, si.sku
			 ORDER BY revenue DESC
			 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$from,
			$to,
			(int) $limit
		) );

		foreach ( (array) $rows as $r ) {
			$r->qty     = (int) $r->qty;
			$r->revenue = round( (float) $r->revenue, 2 );
			$r->profit  = round( (float) $r->profit, 2 );
			$r->margin  = self::margin_pct( $r->revenue, $r->profit );
		}
		return (array) $rows;
	}

	/**
	 * Tax totals aggregated from each sale's stored breakdown, by name+rate.
	 * ponytail: PHP-side aggregation over the range's sales — fine at POS
	 * volumes; switch to MySQL 8 JSON_TABLE if a huge range ever crawls.
	 *
	 * @return array[] { name, rate, amount }
	 */
	public static function tax_summary( $date_from, $date_to, $cashier_id = 0 ) {
		global $wpdb;
		list( $from, $to ) = self::bounds( $date_from, $date_to );
		$sales      = Simple_POS_DB::table( 'sales' );
		$where_cash = self::cashier_sql( $cashier_id );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT tax_breakdown FROM {$sales}
			 WHERE status = 'completed' AND created_at BETWEEN %s AND %s{$where_cash}", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			self::cashier_params( $from, $to, $cashier_id )
		), ARRAY_A );

		$agg = array();
		foreach ( (array) $rows as $r ) {
			$bd = json_decode( (string) $r['tax_breakdown'], true );
			if ( ! is_array( $bd ) ) {
				continue;
			}
			foreach ( $bd as $b ) {
				$name = isset( $b['name'] ) ? (string) $b['name'] : '?';
				$rate = isset( $b['rate'] ) ? (float) $b['rate'] : 0;
				$key  = $name . '|' . $rate;
				if ( ! isset( $agg[ $key ] ) ) {
					$agg[ $key ] = array( 'name' => $name, 'rate' => $rate, 'amount' => 0.0 );
				}
				$agg[ $key ]['amount'] += isset( $b['amount'] ) ? (float) $b['amount'] : 0;
			}
		}
		$out = array_values( $agg );
		usort( $out, function ( $a, $b ) {
			return $b['amount'] <=> $a['amount'];
		} );
		foreach ( $out as $k => $v ) {
			$out[ $k ]['amount'] = round( $v['amount'], 2 );
		}
		return $out;
	}

	/**
	 * Margin percent, guarded against zero revenue.
	 *
	 * @return float
	 */
	public static function margin_pct( $revenue, $profit ) {
		if ( (float) $revenue <= 0 ) {
			return 0.0;
		}
		return round( (float) $profit / (float) $revenue * 100, 2 );
	}

	/**
	 * Build a CSV string (headers + rows), quoted by fputcsv.
	 *
	 * @param string[] $headers Column headers.
	 * @param array[]  $rows    List of row arrays (values only).
	 * @return string
	 */
	public static function to_csv( $headers, $rows ) {
		$out = fopen( 'php://temp', 'r+' );
		if ( false === $out ) {
			return '';
		}
		fputcsv( $out, array_map( 'strval', $headers ) );
		foreach ( (array) $rows as $row ) {
			fputcsv( $out, array_map( 'strval', array_values( (array) $row ) ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );
		return (string) $csv;
	}

	/**
	 * Build a downloadable CSV for a named report type.
	 *
	 * @param string $type      xz|hourly|payments|products|taxes.
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array|WP_Error { csv }
	 */
	public static function export( $type, $date_from, $date_to ) {
		switch ( $type ) {
			case 'xz':
				$d       = self::xz( $date_from, $date_to );
				$headers = array( 'Sales', 'Gross', 'Discount', 'Tax', 'Net', 'Avg basket', 'Items sold', 'Gross profit' );
				return array( 'csv' => self::to_csv( $headers, array( $d ) ) );
			case 'hourly':
				$headers = array( 'Hour', 'Sales', 'Revenue' );
				return array( 'csv' => self::to_csv( $headers, self::hourly( $date_from, $date_to ) ) );
			case 'payments':
				$headers = array( 'Payment method', 'Sales', 'Revenue' );
				return array( 'csv' => self::to_csv( $headers, self::payment_mix( $date_from, $date_to ) ) );
			case 'products':
				$headers = array( 'Product', 'SKU', 'Qty sold', 'Revenue', 'Profit', 'Margin %' );
				return array( 'csv' => self::to_csv( $headers, self::product_mix( $date_from, $date_to, 500 ) ) );
			case 'taxes':
				$headers = array( 'Tax', 'Rate %', 'Amount' );
				return array( 'csv' => self::to_csv( $headers, self::tax_summary( $date_from, $date_to ) ) );
		}
		return new WP_Error( 'spar_unknown_type', __( 'Unknown report type.', 'wp-pos-plugin' ) );
	}
}
