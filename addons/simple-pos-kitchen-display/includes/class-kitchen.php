<?php
/**
 * Kitchen display queue: item-level prep tracking for restaurant POS.
 * Items are inserted when a sale is created; the kitchen staff bumps them
 * when ready. Sales are NOT blocked by kitchen prep — this is an audit trail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKD_Kitchen {

	const ITEMS_TABLE = 'kitchen_items';

	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::ITEMS_TABLE . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, sale_id BIGINT UNSIGNED NOT NULL, sale_number VARCHAR(50) NULL, product_name VARCHAR(191) NOT NULL, qty INT UNSIGNED NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'pending', bumped_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_sale (sale_id) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Tag sale items for kitchen when a POS sale completes.
	 *
	 * @param int   $sale_id
	 * @param array $line_items Cart line items (product_name, qty, etc.).
	 */
	public static function queue_sale_items( $sale_id, $line_items ) {
		global $wpdb;
		self::ensure_schema();

		$sale  = Simple_POS_Sales::get_sale( $sale_id );
		$number = $sale ? $sale->sale_number : '#' . $sale_id;

		$table = Simple_POS_DB::table( self::ITEMS_TABLE );
		$now   = current_time( 'mysql' );

		foreach ( (array) $line_items as $item ) {
			$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
			$qty          = isset( $item['qty'] ) ? max( 1, (int) $item['qty'] ) : 1;
			if ( '' === $product_name || $qty <= 0 ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'sale_id'      => $sale_id,
					'sale_number'  => $number,
					'product_name' => $product_name,
					'qty'          => $qty,
					'status'       => 'pending',
					'created_at'   => $now,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Get pending kitchen orders grouped by sale.
	 *
	 * @param string $status pending|ready|any
	 * @return object[]
	 */
	public static function get_pending_orders( $status = 'pending' ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::ITEMS_TABLE );

		$where = 'bumped_at IS NULL';
		if ( 'ready' === $status ) {
			$where = 'bumped_at IS NOT NULL';
		} elseif ( 'any' !== $status ) {
			$where = 'status = ' . $wpdb->prepare( '%s', $status ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at ASC, id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// Group by sale_id.
		$grouped = array();
		foreach ( $rows as $r ) {
			$sid = (int) $r->sale_id;
			if ( ! isset( $grouped[ $sid ] ) ) {
				$grouped[ $sid ] = array(
					'sale_id'     => $sid,
					'sale_number' => $r->sale_number ?: '#' . $sid,
					'items'       => array(),
				);
			}
			$grouped[ $sid ]['items'][] = $r;
		}

		return array_values( $grouped );
	}

	/**
	 * Bump an entire order: mark all its items ready.
	 *
	 * @param int $sale_id
	 */
	public static function bump_order( $sale_id ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::ITEMS_TABLE );
		$wpdb->update(
			$table,
			array(
				'status'   => 'ready',
				'bumped_at' => current_time( 'mysql' ),
			),
			array( 'sale_id' => (int) $sale_id, 'bumped_at' => null ),
			array( '%s', '%s' ),
			array( '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Bump a single kitchen item.
	 *
	 * @param int    $item_id
	 * @param string $status ready|pending.
	 */
	public static function set_item_status( $item_id, $status ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::ITEMS_TABLE );
		$db_status = 'ready' === $status ? 'ready' : 'pending';
		$wpdb->update(
			$table,
			array(
				'status'    => $db_status,
				'bumped_at' => 'ready' === $status ? current_time( 'mysql' ) : null,
			),
			array( 'id' => (int) $item_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// --- REST callbacks ---

	public static function rest_list( $request ) {
		$status = $request->get_param( 'status' ) ?: 'pending';
		return new WP_REST_Response( self::get_pending_orders( $status ), 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function rest_set_item_status( $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( $request->get_param( 'status' ) );
		self::set_item_status( $id, $status );
		return new WP_REST_Response( array( 'id' => $id, 'status' => $status ), 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function rest_bump_order( $request ) {
		$id = (int) $request['id'];
		self::bump_order( $id );
		return new WP_REST_Response( array( 'sale_id' => $id, 'status' => 'ready' ), 200 );
	}
}