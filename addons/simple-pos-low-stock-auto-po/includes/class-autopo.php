<?php
/**
 * Low-Stock Auto-PO logic.
 * ponytail: core products have no supplier link, so generation makes ONE
 * draft PO using the configured default supplier. Per-supplier grouping
 * needs a product->supplier column — the upgrade path if demand appears.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAPO_AutoPO {

	const OPTION_KEY = 'simple_pos_autopo';

	/**
	 * Settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'      => 1,
			'multiplier'   => 2,
			'min_qty'      => 1,
			'supplier_id'  => 0,
			'notify_email' => '',
			'last_run'     => '',
		);
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * How many units to reorder so stock lands at threshold × multiplier.
	 * Never below min_qty, never negative.
	 *
	 * @param int   $stock      Current stock.
	 * @param int   $threshold  Low-stock threshold.
	 * @param float $multiplier Target multiplier.
	 * @param int   $min_qty    Minimum order qty.
	 * @return int
	 */
	public static function suggest_qty( $stock, $threshold, $multiplier, $min_qty ) {
		$target = (int) ceil( (float) $threshold * (float) $multiplier );
		return max( (int) $min_qty, max( 0, $target - (int) $stock ) );
	}

	/**
	 * Active, stock-tracked products at or below their threshold.
	 *
	 * @return object[]
	 */
	public static function find_low_stock() {
		global $wpdb;
		$products = Simple_POS_DB::table( 'products' );
		return $wpdb->get_results(
			"SELECT id, name, sku, stock_qty, low_stock_threshold, cost_price
			 FROM {$products}
			 WHERE status = 'active' AND track_stock = 1 AND stock_qty <= low_stock_threshold
			 ORDER BY name ASC
			 LIMIT 200" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Product ids among the given ones that already sit on an open PO
	 * (draft/ordered/partial) — prevents the daily cron from stacking
	 * duplicate POs for the same products.
	 *
	 * @param int[] $product_ids.
	 * @return int[]
	 */
	public static function on_open_po( $product_ids ) {
		global $wpdb;
		$product_ids = array_map( 'absint', (array) $product_ids );
		if ( empty( $product_ids ) ) {
			return array();
		}
		$po = Simple_POS_DB::table( 'purchase_orders' );
		$pi = Simple_POS_DB::table( 'po_items' );
		$ids = implode( ',', $product_ids );
		$rows = $wpdb->get_col(
			"SELECT DISTINCT pi.product_id
			 FROM {$pi} pi INNER JOIN {$po} po ON po.id = pi.po_id
			 WHERE po.status IN ('draft','ordered','partial') AND pi.product_id IN ({$ids})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		return array_map( 'intval', (array) $rows );
	}

	private static function touch_last_run() {
		$saved           = get_option( self::OPTION_KEY, array() );
		$saved           = is_array( $saved ) ? $saved : array();
		$saved['last_run'] = current_time( 'mysql' );
		update_option( self::OPTION_KEY, $saved );
	}

	/**
	 * Generate one draft PO covering low-stock products not already on an
	 * open PO. Safe to run repeatedly.
	 *
	 * @return array|WP_Error { created, items, skipped [, po_id] }
	 */
	public static function generate() {
		if ( ! class_exists( 'Simple_POS_Purchase_Orders' ) ) {
			return new WP_Error( 'sapo_no_core', __( 'Simple POS purchase orders not available.', 'simple-pos-autopo' ) );
		}

		$settings = self::get_settings();
		$low      = self::find_low_stock();

		if ( empty( $low ) ) {
			self::touch_last_run();
			return array( 'created' => 0, 'items' => 0, 'skipped' => 0 );
		}

		$open    = self::on_open_po( wp_list_pluck( $low, 'id' ) );
		$items   = array();
		$skipped = 0;
		foreach ( $low as $p ) {
			if ( in_array( (int) $p->id, $open, true ) ) {
				$skipped++;
				continue;
			}
			$items[] = array(
				'product_id' => (int) $p->id,
				'qty'        => self::suggest_qty( $p->stock_qty, $p->low_stock_threshold, $settings['multiplier'], $settings['min_qty'] ),
				'cost_price' => (float) $p->cost_price,
			);
		}

		self::touch_last_run();

		if ( empty( $items ) ) {
			return array( 'created' => 0, 'items' => 0, 'skipped' => $skipped );
		}

		$supplier_id = (int) $settings['supplier_id'];
		$po_id       = Simple_POS_Purchase_Orders::create_order(
			array(
				'supplier_id' => $supplier_id > 0 ? $supplier_id : null,
				'note'        => sprintf(
					/* translators: 1: date/time, 2: product count */
					__( 'Auto-PO generated %1$s for %2$d low-stock product(s).', 'simple-pos-autopo' ),
					current_time( 'mysql' ),
					count( $items )
				),
				'items'       => $items,
			)
		);
		if ( is_wp_error( $po_id ) ) {
			return $po_id;
		}

		self::notify( $settings, $items, $po_id );

		return array( 'created' => 1, 'items' => count( $items ), 'skipped' => $skipped, 'po_id' => $po_id );
	}

	/**
	 * Optional email notification listing what was added to the draft PO.
	 */
	private static function notify( $settings, $items, $po_id ) {
		$email = trim( (string) $settings['notify_email'] );
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}
		$lines = array();
		foreach ( $items as $it ) {
			$lines[] = sprintf( '#%d — qty %d', $it['product_id'], $it['qty'] );
		}
		wp_mail(
			$email,
			__( 'Simple POS: auto-PO drafted', 'simple-pos-autopo' ),
			sprintf(
				/* translators: 1: PO id, 2: newline list */
				__( 'Draft PO #%1$s was created with %2$s', 'simple-pos-autopo' ),
				$po_id,
				"\n" . implode( "\n", $lines )
			)
		);
	}

	/**
	 * Daily cron entry point.
	 */
	public static function run_daily() {
		if ( class_exists( 'Simple_POS_Addons' ) && ! Simple_POS_Addons::is_enabled( 'low-stock-auto-po' ) ) {
			return;
		}
		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		self::generate();
	}
}
