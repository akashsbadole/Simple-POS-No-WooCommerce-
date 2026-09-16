<?php
/**
 * Customer data access. Customers are optional — sales can be recorded
 * against a "walk-in" (customer_id = null) as well as a saved customer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Customers {

	/**
	 * Search/list customers.
	 *
	 * @param string $search   Search term (name, phone, email).
	 * @param int    $per_page Default 20.
	 * @param int    $page     Default 1.
	 * @return array { items, total }
	 */
	public static function get_customers( $search = '', $per_page = 20, $page = 1 ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'customers' );

		$where  = '1=1';
		$params = array();

		if ( ! empty( $search ) ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where  = '(name LIKE %s OR phone LIKE %s OR email LIKE %s)';
			$params = array( $like, $like, $like );
		}

		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $total_sql, $params ) : $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$per_page = max( 1, min( Simple_POS_DB::MAX_PER_PAGE, (int) $per_page ) );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;

		$sql          = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get a single customer.
	 *
	 * @param int $id Customer ID.
	 * @return object|null
	 */
	public static function get_customer( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'customers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create a customer.
	 *
	 * @param array $data Raw input.
	 * @return int|WP_Error
	 */
	public static function create_customer( $data ) {
		global $wpdb;

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( empty( $name ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Customer name is required.', 'simple-pos' ) );
		}

		$table  = Simple_POS_DB::table( 'customers' );
		$insert = array(
			'name'       => $name,
			'phone'      => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'email'      => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'address'    => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '',
			'notes'      => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'created_at' => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( $table, $insert ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $inserted ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create customer.', 'simple-pos' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a customer.
	 *
	 * @param int   $id   Customer ID.
	 * @param array $data Raw input.
	 * @return true|WP_Error
	 */
	public static function update_customer( $id, $data ) {
		global $wpdb;
		$existing = self::get_customer( $id );
		if ( ! $existing ) {
			return new WP_Error( 'pos_not_found', __( 'Customer not found.', 'simple-pos' ) );
		}

		$table  = Simple_POS_DB::table( 'customers' );
		$update = array(
			'name'    => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $existing->name,
			'phone'   => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : $existing->phone,
			'email'   => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : $existing->email,
			'address' => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : $existing->address,
			'notes'   => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : $existing->notes,
		);

		$wpdb->update( $table, $update, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return true;
	}

	/**
	 * Delete a customer. Their past sales keep customer_id but the row
	 * is gone — sale display should handle a missing customer gracefully.
	 *
	 * @param int $id Customer ID.
	 * @return true
	 */
	public static function delete_customer( $id ) {
		global $wpdb;
		// Nullify customer reference in sales before deleting to prevent orphaned records.
		$sales_table = Simple_POS_DB::table( 'sales' );
		$wpdb->update( $sales_table, array( 'customer_id' => null ), array( 'customer_id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$table = Simple_POS_DB::table( 'customers' );
		$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return true;
	}

	/**
	 * Get a customer's purchase history.
	 *
	 * @param int $id    Customer ID.
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function get_purchase_history( $id, $limit = 20 ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'sales' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id,
				$limit
			)
		);
	}
}
