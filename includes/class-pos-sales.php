<?php
/**
 * Sales transaction logic — the heart of the checkout flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Sales {

	/**
	 * Create a sale from a cart.
	 *
	 * Expected $cart_data shape:
	 * array(
	 *   'items' => array(
	 *     array( 'product_id' => 12, 'qty' => 2, 'price' => 9.99 [optional override] ),
	 *     ...
	 *   ),
	 *   'customer_id'     => 0,
	 *   'discount_type'   => 'fixed'|'percent',
	 *   'discount_amount' => 0,
	 *   'payment_method'  => 'cash',
	 *   'amount_paid'     => 0,
	 *   'note'            => '',
	 * )
	 *
	 * @param array $cart_data Cart payload.
	 * @return int|WP_Error New sale ID, or error (nothing is written on error).
	 */
	public static function create_sale( $cart_data ) {
		if ( empty( $cart_data['items'] ) || ! is_array( $cart_data['items'] ) ) {
			return new WP_Error( 'pos_empty_cart', __( 'Cart is empty.', 'simple-pos' ) );
		}
		$settings = Simple_POS_Settings::get_all();
		$tax_country = isset($cart_data['tax_country']) ? strtoupper(sanitize_text_field($cart_data['tax_country'])) : (isset($settings['tax_country'])? strtoupper($settings['tax_country']):'US');
		$tax_state = isset($cart_data['tax_state']) ? strtoupper(sanitize_text_field($cart_data['tax_state'])) : (isset($settings['tax_state'])? strtoupper($settings['tax_state']):'');
		if (empty($tax_country)) $tax_country='US';

		// Resolve line items - support variant_id
		$order_lines = array();
		$line_items = array();
		foreach ( $cart_data['items'] as $raw_item ) {
			$product_id = isset( $raw_item['product_id'] ) ? (int) $raw_item['product_id'] : 0;
			$variant_id = isset( $raw_item['variant_id'] ) ? (int) $raw_item['variant_id'] : 0;
			$qty        = isset( $raw_item['qty'] ) ? (int) $raw_item['qty'] : 0;
			if ( $product_id <= 0 || $qty <= 0 ) {
				return new WP_Error( 'pos_invalid_item', __( 'Invalid item in cart.', 'simple-pos' ) );
			}
			$product = Simple_POS_Products::get_product( $product_id );
			if ( ! $product || 'active' !== $product->status ) {
				return new WP_Error( 'pos_invalid_item', sprintf( __( 'Product #%d is not available.', 'simple-pos' ), $product_id ) );
			}
			$variant = null;
			if ( $variant_id ) {
				$variant = Simple_POS_Variants::get_variant($variant_id);
				if ( ! $variant || (int)$variant->parent_product_id !== $product_id || 'active' !== $variant->status ) {
					return new WP_Error( 'pos_invalid_item', __( 'Variant not available.', 'simple-pos' ) );
				}
			}
			$price = null !== ($variant->price ?? null) ? (float)$variant->price : (float)$product->price;
			$cost_price = null !== ($variant->cost_price ?? null) ? (float)$variant->cost_price : (float)$product->cost_price;
			$sku = $variant ? ($variant->sku ?: $product->sku) : $product->sku;
			$product_name = $variant ? ($product->name . ' — ' . Simple_POS_Variants::variant_label($variant)) : $product->name;
			$track_stock = $variant ? (int)$variant->track_stock : (int)$product->track_stock;
			$stock_qty = $variant ? (int)$variant->stock_qty : (int)$product->stock_qty;
			$tax_class_id = (int)($product->tax_class_id ?: 0);
			$order_lines[] = array('price'=>$price,'qty'=>$qty,'class_id'=>$tax_class_id);
			$line_items[] = array(
				'product_id'   => $product_id,
				'variant_id'   => $variant_id ?: null,
				'product_name' => $product_name,
				'sku'          => $sku,
				'qty'          => $qty,
				'price'        => $price,
				'cost_price'   => $cost_price,
				'track_stock'  => $track_stock,
				'stock_qty'    => $stock_qty,
				'tax_class_id' => $tax_class_id,
			);
		}
		$discount_type   = ( isset( $cart_data['discount_type'] ) && 'percent' === $cart_data['discount_type'] ) ? 'percent' : 'fixed';
		$discount_input  = isset( $cart_data['discount_amount'] ) ? max( 0, (float) $cart_data['discount_amount'] ) : 0;
		// Use tax engine to compute totals
		if ( ! class_exists('Simple_POS_Tax') ) require_once SIMPLE_POS_PLUGIN_DIR.'includes/class-pos-tax.php';
		$calc = Simple_POS_Tax::calculate_order($order_lines, $tax_country, $tax_state, $discount_type, $discount_input);
		$subtotal = $calc['subtotal'];
		$discount_amount = $calc['discount'];
		$tax_total = $calc['tax'];
		$total = $calc['total'];
		$tax_breakdown = $calc['breakdown'];

		$payment_method = isset( $cart_data['payment_method'] ) ? sanitize_text_field( $cart_data['payment_method'] ) : 'cash';
		$amount_paid    = isset( $cart_data['amount_paid'] ) ? max( 0, (float) $cart_data['amount_paid'] ) : $total;
		$change_due     = max( 0, round( $amount_paid - $total, 2 ) );
		$customer_id    = ! empty( $cart_data['customer_id'] ) ? (int) $cart_data['customer_id'] : null;
		$note           = isset( $cart_data['note'] ) ? sanitize_textarea_field( $cart_data['note'] ) : '';
		$currency_code = isset($settings['currency_code'])? $settings['currency_code']:'USD';

		return Simple_POS_DB::transaction( function () use (
			$line_items, $subtotal, $discount_type, $discount_amount, $tax_total,
			$total, $payment_method, $amount_paid, $change_due, $customer_id, $note, $tax_country, $tax_state, $tax_breakdown, $currency_code, $calc
		) {
			global $wpdb;
			$settings = Simple_POS_Settings::get_all();
			foreach ( $line_items as $item ) {
				if ( ! $item['track_stock'] ) continue;
				$available = (int)$item['stock_qty'];
				if ( ( $available - $item['qty'] ) < 0 && empty( $settings['allow_negative_stock'] ) ) {
					return new WP_Error( 'pos_insufficient_stock', sprintf( __( 'Not enough stock for "%s".', 'simple-pos' ), $item['product_name'] ) );
				}
			}
			$sales_table = Simple_POS_DB::table( 'sales' );
			$items_table = Simple_POS_DB::table( 'sale_items' );
			$sale_number = Simple_POS_DB::next_sale_number();
			$inserted = $wpdb->insert(
				$sales_table,
				array(
					'sale_number'     => $sale_number,
					'customer_id'     => $customer_id,
					'cashier_id'      => get_current_user_id(),
					'subtotal'        => round( $subtotal, 2 ),
					'discount_type'   => $discount_type,
					'discount_amount' => $discount_amount,
					'tax_amount'      => round( $tax_total, 2 ),
					'total'           => $total,
					'amount_paid'     => $amount_paid,
					'change_due'      => $change_due,
					'payment_method'  => $payment_method,
					'status'          => 'completed',
					'note'            => $note,
					'tax_country'     => $tax_country,
					'tax_state'       => $tax_state,
					'tax_breakdown'   => wp_json_encode($tax_breakdown),
					'currency_code'   => $currency_code,
					'exchange_rate'   => 1,
					'created_at'      => current_time( 'mysql' ),
				)
			);
			if ( false === $inserted ) {
				return new WP_Error( 'pos_db_error', __( 'Could not record sale.', 'simple-pos' ) );
			}
			$sale_id = (int) $wpdb->insert_id;
			foreach ( $line_items as $idx=>$item ) {
				$line_calc = $calc['lines'][$idx];
				$wpdb->insert(
					$items_table,
					array(
						'sale_id'      => $sale_id,
						'product_id'   => $item['product_id'],
						'variant_id'   => $item['variant_id'],
						'product_name' => $item['product_name'],
						'sku'          => $item['sku'],
						'qty'          => $item['qty'],
						'price'        => $item['price'],
						'cost_price'   => $item['cost_price'],
						'tax_amount'   => $line_calc['tax_amount'],
						'tax_class_id' => $item['tax_class_id'],
						'tax_breakdown'=> wp_json_encode($line_calc['breakdown']),
						'tax_rate_applied'=> isset($line_calc['breakdown'][0]['rate'])? $line_calc['breakdown'][0]['rate']:0,
						'line_total'   => $line_calc['gross'],
					)
				);
				if ( $item['track_stock'] ) {
					if ( $item['variant_id'] ) {
						Simple_POS_Variants::adjust_stock( $item['variant_id'], -1 * $item['qty'], 'sale', $sale_id );
					} else {
						Simple_POS_Products::adjust_stock( $item['product_id'], -1 * $item['qty'], 'sale', $sale_id );
					}
				}
			}
			return $sale_id;
		} );
	}

	/**
	 * Wrapper that also busts report caches on success. This is what the
	 * REST API and terminal should call instead of create_sale() directly.
	 *
	 * @param array $cart_data Cart payload.
	 * @return int|WP_Error
	 */
	public static function checkout( $cart_data ) {
		$result = self::create_sale( $cart_data );
		if ( ! is_wp_error( $result ) ) {
			Simple_POS_Reports::flush_cache();
		}
		return $result;
	}

	/**
	 * Void a completed sale: marks it voided and restores stock for every
	 * tracked line item.
	 *
	 * @param int    $sale_id Sale ID.
	 * @param string $note    Optional reason.
	 * @return true|WP_Error
	 */
	public static function void_sale( $sale_id, $note = '' ) {
		global $wpdb;

		$sale = self::get_sale( $sale_id );
		if ( ! $sale ) {
			return new WP_Error( 'pos_not_found', __( 'Sale not found.', 'simple-pos' ) );
		}
		if ( 'completed' !== $sale->status ) {
			return new WP_Error( 'pos_invalid_state', __( 'Only completed sales can be voided.', 'simple-pos' ) );
		}

		return Simple_POS_DB::transaction( function () use ( $sale_id, $sale, $note ) {
			global $wpdb;
			$sales_table = Simple_POS_DB::table( 'sales' );
			$items       = self::get_sale_items( $sale_id );

			foreach ( $items as $item ) {
				if ( $item->variant_id ) {
					$variant = Simple_POS_Variants::get_variant( $item->variant_id );
					if ( $variant && $variant->track_stock ) {
						Simple_POS_Variants::adjust_stock( $item->variant_id, (int) $item->qty, 'void', $sale_id, $note );
					}
				} elseif ( $item->product_id ) {
					$product = Simple_POS_Products::get_product( $item->product_id );
					if ( $product && $product->track_stock ) {
						Simple_POS_Products::adjust_stock( $item->product_id, (int) $item->qty, 'void', $sale_id, $note );
					}
				}
			}

			$updated = $wpdb->update( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sales_table,
				array(
					'status' => 'voided',
					'note'   => trim( $sale->note . ' ' . $note ),
				),
				array( 'id' => $sale_id )
			);

			if ( false === $updated ) {
				return new WP_Error( 'pos_db_error', __( 'Could not void sale.', 'simple-pos' ) );
			}

			Simple_POS_Reports::flush_cache();

			return true;
		} );
	}

	/**
	 * Get a single sale.
	 *
	 * @param int $id Sale ID.
	 * @return object|null
	 */
	public static function get_sale( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'sales' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get line items for a sale.
	 *
	 * @param int $sale_id Sale ID.
	 * @return array
	 */
	public static function get_sale_items( $sale_id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'sale_items' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE sale_id = %d", $sale_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * List/filter sales history.
	 *
	 * @param array $args {
	 *     @type string $date_from  Y-m-d.
	 *     @type string $date_to    Y-m-d.
	 *     @type int    $cashier_id Filter by cashier.
	 *     @type string $status     completed|voided|any. Default any.
	 *     @type int    $per_page   Default 20.
	 *     @type int    $page       Default 1.
	 * }
	 * @return array { items, total }
	 */
	public static function get_sales( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date_from'  => '',
			'date_to'    => '',
			'cashier_id' => 0,
			'status'     => 'any',
			'per_page'   => 20,
			'page'       => 1,
		);
		$args  = wp_parse_args( $args, $defaults );
		$table = Simple_POS_DB::table( 'sales' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( $args['date_from'] ) );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = gmdate( 'Y-m-d 23:59:59', strtotime( $args['date_to'] ) );
		}
		if ( ! empty( $args['cashier_id'] ) ) {
			$where[]  = 'cashier_id = %d';
			$params[] = (int) $args['cashier_id'];
		}
		if ( 'any' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$sql          = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
