<?php
/**
 * Online orders: public order intake + admin/terminal workflow.
 * Orders are captured here; stock is only touched when the owner rings the
 * order through the terminal (normal checkout flow).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Soo_Orders {

	const TABLE_SUFFIX = 'online_orders';

	const STATUS_PENDING    = 'pending';
	const STATUS_FULFILLED  = 'fulfilled';
	const STATUS_CANCELLED  = 'cancelled';

	public static function ensure_schema() {
		global $wpdb;
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$prefix  = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TABLE_SUFFIX . "` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, customer_name VARCHAR(191) NOT NULL, customer_phone VARCHAR(50) NULL, customer_email VARCHAR(191) NULL, delivery_address TEXT NULL, items TEXT NOT NULL, subtotal DECIMAL(12,2) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id) ) {$charset}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @return object[]
	 */
	public static function get_orders( $status = 'any' ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		if ( in_array( $status, array( self::STATUS_PENDING, self::STATUS_FULFILLED, self::STATUS_CANCELLED ), true ) ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "SELECT * FROM {$table} ORDER BY created_at DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @param int $id
	 * @return object|null
	 */
	public static function get_order( $id ) {
		global $wpdb;
		self::ensure_schema();
		$table = Simple_POS_DB::table( self::TABLE_SUFFIX );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Validate + store an order from the public REST endpoint.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_create( $request ) {
		$name  = sanitize_text_field( $request->get_param( 'customer_name' ) );
		$phone = sanitize_text_field( $request->get_param( 'customer_phone' ) );
		$email = sanitize_email( $request->get_param( 'customer_email' ) );
		$addr  = sanitize_textarea_field( $request->get_param( 'delivery_address' ) );

		if ( '' === $name ) {
			return new WP_Error( 'soo_missing_name', __( 'Name is required.', 'simple-pos' ), array( 'status' => 400 ) );
		}

		$items      = $request->get_param( 'items' );
		$clean      = array();
		$subtotal   = 0.0;
		foreach ( (array) $items as $raw ) {
			$product_id = isset( $raw['product_id'] ) ? (int) $raw['product_id'] : 0;
			$qty        = isset( $raw['qty'] ) ? (int) $raw['qty'] : 0;
			$product    = $product_id ? Simple_POS_Products::get_product( $product_id ) : null;
			if ( ! $product || $qty <= 0 ) {
				continue;
			}
			$clean[] = array(
				'product_id'   => $product_id,
				'name'         => $product->name,
				'qty'          => $qty,
				'price'        => (float) $product->price,
			);
			$subtotal += (float) $product->price * $qty;
		}

		if ( empty( $clean ) ) {
			return new WP_Error( 'soo_empty_order', __( 'Your order is empty.', 'simple-pos' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		self::ensure_schema();
		$wpdb->insert(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array(
				'customer_name'    => $name,
				'customer_phone'   => $phone,
				'customer_email'   => $email,
				'delivery_address' => $addr,
				'items'            => wp_json_encode( $clean ),
				'subtotal'         => $subtotal,
				'status'           => self::STATUS_PENDING,
				'created_at'       => current_time( 'mysql' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$id = (int) $wpdb->insert_id;
		do_action( 'simple_pos_online_order_placed', $id );

		return new WP_REST_Response(
			array(
				'id'      => $id,
				'number'  => 'ONLINE-' . $id,
				'status'  => self::STATUS_PENDING,
			),
			201
		);
	}

	/**
	 * REST list for terminal/admin (JSON).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function rest_list( $request ) {
		$orders = self::get_orders( $request->get_param( 'status' ) ?: 'pending' );
		$out    = array();
		foreach ( $orders as $o ) {
			$out[] = array(
				'id'        => (int) $o->id,
				'number'    => 'ONLINE-' . $o->id,
				'customer'  => $o->customer_name,
				'phone'     => $o->customer_phone,
				'subtotal'  => (float) $o->subtotal,
				'status'    => $o->status,
				'created'   => $o->created_at,
				'items'     => json_decode( $o->items, true ),
			);
		}
		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * REST status update.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_set_status( $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( $request->get_param( 'status' ) );
		if ( ! in_array( $status, array( self::STATUS_PENDING, self::STATUS_FULFILLED, self::STATUS_CANCELLED ), true ) ) {
			return new WP_Error( 'soo_bad_status', __( 'Invalid status.', 'simple-pos' ), array( 'status' => 400 ) );
		}
		self::set_status( $id, $status );
		return new WP_REST_Response( array( 'id' => $id, 'status' => $status ), 200 );
	}

	/**
	 * @param int    $id
	 * @param string $status
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		self::ensure_schema();
		$wpdb->update(
			Simple_POS_DB::table( self::TABLE_SUFFIX ),
			array( 'status' => sanitize_key( $status ) ),
			array( 'id' => (int) $id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return true;
	}
}