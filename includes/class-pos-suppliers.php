<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Suppliers {
	public static function get_suppliers() {
		global $wpdb;
		$table = Simple_POS_DB::table( 'suppliers' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	public static function get_supplier( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'suppliers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
	}
	public static function create_supplier( $data ) {
		global $wpdb;
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( empty( $name ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Supplier name required.', 'wp-pos-plugin' ) );
		}
		$table = Simple_POS_DB::table( 'suppliers' );
		$wpdb->insert(
			$table,
			array(
				'name'         => $name,
				'contact_name' => isset( $data['contact_name'] ) ? sanitize_text_field( $data['contact_name'] ) : '',
				'phone'        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
				'email'        => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
				'address'      => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '',
				'created_at'   => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}
	public static function update_supplier( $id, $data ) {
		global $wpdb;
		$table    = Simple_POS_DB::table( 'suppliers' );
		$existing = self::get_supplier( $id );
		if ( ! $existing ) {
			return new WP_Error( 'pos_not_found', __( 'Supplier not found.', 'wp-pos-plugin' ) );
		}
		$upd = array(
			'name'         => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $existing->name,
			'contact_name' => isset( $data['contact_name'] ) ? sanitize_text_field( $data['contact_name'] ) : $existing->contact_name,
			'phone'        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : $existing->phone,
			'email'        => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : $existing->email,
			'address'      => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : $existing->address,
		);
		if ( empty( $upd['name'] ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Supplier name required.', 'wp-pos-plugin' ) );
		}
		$wpdb->update( $table, $upd, array( 'id' => $id ) );
		return true;
	}
	public static function delete_supplier( $id ) {
		global $wpdb;
		$wpdb->delete( Simple_POS_DB::table( 'suppliers' ), array( 'id' => $id ) );
		return true;
	}
}

class Simple_POS_Purchase_Orders {
	public static function next_po_number() {
		global $wpdb;
		$settings = Simple_POS_Settings::get_all();
		$prefix   = isset( $settings['po_number_prefix'] ) ? $settings['po_number_prefix'] : 'PO-';
		$table    = Simple_POS_DB::table( 'purchase_orders' );
		$max      = $wpdb->get_var( "SELECT MAX(id) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$next     = $max ? ( (int) $max + 1 ) : 1;
		// Guard against reuse/collisions (deleted latest PO, prefix
		// changes, concurrent creates): advance until free, so the
		// UNIQUE(po_number) insert below cannot fail on a duplicate.
		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$candidate = $prefix . str_pad( $next, 6, '0', STR_PAD_LEFT );
			$exists    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE po_number = %s", $candidate ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( 0 === $exists ) {
				return $candidate;
			}
			$next++;
		}
		return $prefix . str_pad( $next, 6, '0', STR_PAD_LEFT );
	}

	public static function get_orders( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'status'   => 'any',
			'per_page' => 20,
			'page'     => 1,
		);
		$args     = wp_parse_args( $args, $defaults );
		$table    = Simple_POS_DB::table( 'purchase_orders' );
		$where    = '1=1';
		$params   = array();
		if ( 'any' !== $args['status'] ) {
			$where   .= ' AND status=%s';
			$params[] = $args['status']; }
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $total_sql, $params ) : $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$per_page  = max( 1, min( 100, (int) $args['per_page'] ) );
		$page      = max( 1, (int) $args['page'] );
		$offset    = ( $page - 1 ) * $per_page;
		$sql       = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$qparams   = array_merge( $params, array( $per_page, $offset ) );
		$items     = $wpdb->get_results( $wpdb->prepare( $sql, $qparams ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'items' => $items,
			'total' => $total,
		);
	}
	public static function get_order( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'purchase_orders' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
	}
	public static function get_items( $po_id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'po_items' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE po_id=%d", $po_id ) );
	}
	public static function create_order( $data ) {
		global $wpdb;
		$supplier_id = ! empty( $data['supplier_id'] ) ? (int) $data['supplier_id'] : null;
		$note        = isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '';
		$items       = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
		if ( empty( $items ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Add at least one item.', 'wp-pos-plugin' ) );
		}
		$po_table   = Simple_POS_DB::table( 'purchase_orders' );
		$item_table = Simple_POS_DB::table( 'po_items' );
		$po_number  = self::next_po_number();
		$total_cost = 0;
		foreach ( $items as $it ) {
			$total_cost += (float) ( $it['cost_price'] ?? 0 ) * (int) ( $it['qty'] ?? 0 ); }
		$result = Simple_POS_DB::transaction(
			function () use ( $po_table, $item_table, $supplier_id, $note, $items, $po_number, $total_cost ) {
				global $wpdb;
				$wpdb->insert(
					$po_table,
					array(
					'po_number'   => $po_number,
					'supplier_id' => $supplier_id,
					'status'      => 'draft',
					'total_cost'  => Simple_POS_Tax::pos_round( $total_cost, 2 ),
						'note'        => $note,
						'created_at'  => current_time( 'mysql' ),
						'created_by'  => get_current_user_id(),
					)
				);
				$po_id = (int) $wpdb->insert_id;
				if ( ! $po_id ) {
					return new WP_Error( 'pos_db_error', __( 'Could not create PO.', 'wp-pos-plugin' ) );
				}
				foreach ( $items as $it ) {
					$product_id = ! empty( $it['product_id'] ) ? (int) $it['product_id'] : null;
					$variant_id = ! empty( $it['variant_id'] ) ? (int) $it['variant_id'] : null;
					if ( ! $product_id && ! $variant_id ) {
						continue;
					}
					$qty = (int) ( $it['qty'] ?? 1 );
					if ( $qty <= 0 ) {
						$qty = 1;
					}
					$cost = (float) ( $it['cost_price'] ?? 0 );
					$wpdb->insert(
						$item_table,
						array(
							'po_id'        => $po_id,
							'product_id'   => $product_id,
							'variant_id'   => $variant_id,
							'qty'          => $qty,
							'cost_price'   => $cost,
							'received_qty' => 0,
						)
					);
				}
				return $po_id;
			}
		);
		return $result;
	}
	
	public static function update_order( $id, $data ) {
		global $wpdb;
		$order = self::get_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'pos_not_found', __( 'PO not found.', 'wp-pos-plugin' ) );
		}
		
		// Only allow editing draft POs.
		if ( $order->status !== 'draft' ) {
			return new WP_Error( 'pos_invalid_state', __( 'Can only edit draft POs.', 'wp-pos-plugin' ) );
		}
		
		$supplier_id = isset( $data['supplier_id'] ) ? (int) $data['supplier_id'] : $order->supplier_id;
		$note        = isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : $order->note;
		$items       = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : null;
		
		$po_table   = Simple_POS_DB::table( 'purchase_orders' );
		$item_table = Simple_POS_DB::table( 'po_items' );
		
		return Simple_POS_DB::transaction(
			function () use ( $id, $po_table, $item_table, $supplier_id, $note, $items, $order ) {
				global $wpdb;
				
				// Update PO header.
				$wpdb->update(
					$po_table,
					array(
						'supplier_id' => $supplier_id,
						'note'        => $note,
					),
					array( 'id' => $id )
				);
				
				// Update items if provided.
				if ( null !== $items ) {
					// Delete existing items.
					$wpdb->delete( $item_table, array( 'po_id' => $id ) );
					
					// Recalculate total cost.
					$total_cost = 0;
					foreach ( $items as $it ) {
						$product_id = ! empty( $it['product_id'] ) ? (int) $it['product_id'] : null;
						$variant_id = ! empty( $it['variant_id'] ) ? (int) $it['variant_id'] : null;
						if ( ! $product_id && ! $variant_id ) {
							continue;
						}
						$qty  = max( 1, (int) ( $it['qty'] ?? 1 ) );
						$cost = (float) ( $it['cost_price'] ?? 0 );
						$total_cost += $cost * $qty;
						
						$wpdb->insert(
							$item_table,
							array(
								'po_id'        => $id,
								'product_id'   => $product_id,
								'variant_id'   => $variant_id,
								'qty'          => $qty,
								'cost_price'   => $cost,
								'received_qty' => 0,
							)
						);
					}
					
					// Update total cost.
				$wpdb->update(
					$po_table,
					array( 'total_cost' => Simple_POS_Tax::pos_round( $total_cost, 2 ) ),
					array( 'id' => $id )
				);
				}
				
				return true;
			}
		);
	}
	
	public static function update_status( $id, $status ) {
		global $wpdb;
		$allowed = array( 'draft', 'ordered', 'partial', 'received', 'cancelled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Invalid status.', 'wp-pos-plugin' ) );
		}
		$table = Simple_POS_DB::table( 'purchase_orders' );
		$upd   = array( 'status' => $status );
		if ( 'ordered' === $status ) {
			$upd['ordered_at'] = current_time( 'mysql' );
		}
		if ( 'received' === $status ) {
			$upd['received_at'] = current_time( 'mysql' );
		}
		$wpdb->update( $table, $upd, array( 'id' => $id ) );
		return true;
	}
	public static function receive( $id, $receive_data = array() ) {
		global $wpdb;
		$order = self::get_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'pos_not_found', __( 'PO not found.', 'wp-pos-plugin' ) );
		}
		if ( in_array( $order->status, array( 'cancelled', 'received' ), true ) ) {
			return new WP_Error( 'pos_invalid_state', __( 'PO already closed.', 'wp-pos-plugin' ) );
		}
		$items = self::get_items( $id );
		$map   = array();
		foreach ( $items as $it ) {
			$map[ $it->id ] = $it;
		}
		return Simple_POS_DB::transaction(
			function () use ( $id, $receive_data, $map, $order ) {
				global $wpdb;
				$po_items_table = Simple_POS_DB::table( 'po_items' );
				$all_received   = true;
				foreach ( $receive_data as $r ) {
					$item_id = (int) ( $r['item_id'] ?? 0 );
					$qty     = (int) ( $r['received_qty'] ?? 0 );
					if ( ! isset( $map[ $item_id ] ) ) {
						continue;
					}
					$item       = $map[ $item_id ];
					$to_receive = $qty > 0 ? $qty : ( $item->qty - $item->received_qty );
					$to_receive = max( 0, min( $to_receive, $item->qty - $item->received_qty ) );
					if ( $to_receive <= 0 ) {
						continue;
					}
					$wpdb->query( $wpdb->prepare( "UPDATE {$po_items_table} SET received_qty = received_qty + %d WHERE id=%d", $to_receive, $item_id ) );
					if ( $item->variant_id ) {
						$stock_result = Simple_POS_Variants::adjust_stock( $item->variant_id, $to_receive, 'purchase', $id, 'PO ' . $order->po_number );
						if ( is_wp_error( $stock_result ) ) {
							return $stock_result;
						}
						if ( $item->cost_price ) {
							$cost_result = Simple_POS_Variants::update_variant( $item->variant_id, array( 'cost_price' => $item->cost_price ) );
							if ( is_wp_error( $cost_result ) ) {
								return $cost_result;
							}
						}
					} elseif ( $item->product_id ) {
						$stock_result = Simple_POS_Products::adjust_stock( $item->product_id, $to_receive, 'purchase', $id, 'PO ' . $order->po_number );
						if ( is_wp_error( $stock_result ) ) {
							return $stock_result;
						}
						if ( $item->cost_price ) {
							$cost_result = Simple_POS_Products::update_product( $item->product_id, array( 'cost_price' => $item->cost_price ) );
							if ( is_wp_error( $cost_result ) ) {
								return $cost_result;
							}
						}
					}
				}
				// Check if fully received
				$remaining = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(qty - received_qty) FROM {$po_items_table} WHERE po_id=%d", $id ) );
				$status    = ( (int) $remaining <= 0 ) ? 'received' : 'partial';
				$wpdb->update(
					Simple_POS_DB::table( 'purchase_orders' ),
					array(
						'status'      => $status,
						'received_at' => current_time( 'mysql' ),
					),
					array( 'id' => $id )
				);
				return true;
			}
		);
	}
	public static function delete_order( $id ) {
		global $wpdb;
		$wpdb->delete( Simple_POS_DB::table( 'po_items' ), array( 'po_id' => $id ) );
		$wpdb->delete( Simple_POS_DB::table( 'purchase_orders' ), array( 'id' => $id ) );
		return true;
	}
}
