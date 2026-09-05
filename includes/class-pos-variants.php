<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Variants {

	public static function get_variants( $parent_id, $status = 'any' ) {
		global $wpdb;
		$table  = Simple_POS_DB::table( 'product_variants' );
		$where  = 'parent_product_id=%d';
		$params = array( $parent_id );
		if ( 'any' !== $status ) {
			$where   .= ' AND status=%s';
			$params[] = $status; }
		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function get_variant( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'product_variants' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
	}

	public static function create_variant( $parent_id, $data ) {
		global $wpdb;
		$table  = Simple_POS_DB::table( 'product_variants' );
		$parent = Simple_POS_Products::get_product( $parent_id );
		if ( ! $parent ) {
			return new WP_Error( 'pos_not_found', __( 'Parent product not found.', 'simple-pos' ) );
		}
		$clean = self::sanitize( $data );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		if ( ! empty( $clean['sku'] ) && self::sku_exists( $clean['sku'] ) ) {
			return new WP_Error( 'pos_duplicate_sku', __( 'SKU already used.', 'simple-pos' ) );
		}
		if ( ! empty( $clean['barcode'] ) && self::barcode_exists( $clean['barcode'] ) ) {
			return new WP_Error( 'pos_duplicate_barcode', __( 'Barcode already used.', 'simple-pos' ) );
		}
		$clean['parent_product_id'] = $parent_id;
		$clean['created_at']        = current_time( 'mysql' );
		$clean['updated_at']        = current_time( 'mysql' );
		$inserted                   = $wpdb->insert( $table, $clean );
		if ( false === $inserted ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create variant.', 'simple-pos' ) );
		}
		$id = (int) $wpdb->insert_id;
		if ( (int) $clean['stock_qty'] > 0 ) {
			Simple_POS_Products::log_stock_change( $parent_id, (int) $clean['stock_qty'], (int) $clean['stock_qty'], 'initial', null, get_current_user_id(), 'Initial variant stock', $id );
		}
		return $id;
	}

	public static function update_variant( $id, $data ) {
		global $wpdb;
		$table    = Simple_POS_DB::table( 'product_variants' );
		$existing = self::get_variant( $id );
		if ( ! $existing ) {
			return new WP_Error( 'pos_not_found', __( 'Variant not found.', 'simple-pos' ) );
		}
		$clean = self::sanitize( $data, $existing );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		if ( ! empty( $clean['sku'] ) && self::sku_exists( $clean['sku'], $id ) ) {
			return new WP_Error( 'pos_duplicate_sku', __( 'SKU already used.', 'simple-pos' ) );
		}
		if ( ! empty( $clean['barcode'] ) && self::barcode_exists( $clean['barcode'], $id ) ) {
			return new WP_Error( 'pos_duplicate_barcode', __( 'Barcode already used.', 'simple-pos' ) );
		}
		$old_qty             = (int) $existing->stock_qty;
		$new_qty             = (int) $clean['stock_qty'];
		$clean['updated_at'] = current_time( 'mysql' );
		$wpdb->update( $table, $clean, array( 'id' => $id ) );
		if ( $new_qty !== $old_qty ) {
			Simple_POS_Products::log_stock_change( $existing->parent_product_id, $new_qty - $old_qty, $new_qty, 'adjustment', null, get_current_user_id(), 'Variant manual edit', $id );
		}
		return true;
	}

	public static function delete_variant( $id ) {
		global $wpdb;
		$items = Simple_POS_DB::table( 'sale_items' );
		$used  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$items} WHERE variant_id=%d", $id ) );
		$table = Simple_POS_DB::table( 'product_variants' );
		if ( $used > 0 ) {
			$wpdb->update( $table, array( 'status' => 'inactive' ), array( 'id' => $id ) );
			return true;
		}
		$wpdb->delete( $table, array( 'id' => $id ) );
		return true;
	}

	public static function adjust_stock( $variant_id, $delta, $reason = 'adjustment', $reference_id = null, $note = '' ) {
		global $wpdb;
		$variant = self::get_variant( $variant_id );
		if ( ! $variant ) {
			return new WP_Error( 'pos_not_found', __( 'Variant not found.', 'simple-pos' ) );
		}
		if ( ! $variant->track_stock ) {
			return true;
		}
		$settings = Simple_POS_Settings::get_all();
		$new_qty  = (int) $variant->stock_qty + (int) $delta;
		if ( $new_qty < 0 && empty( $settings['allow_negative_stock'] ) ) {
			return new WP_Error( 'pos_insufficient_stock', sprintf( __( 'Not enough stock for variant "%s".', 'simple-pos' ), self::variant_label( $variant ) ) );
		}
		$table = Simple_POS_DB::table( 'product_variants' );
		$wpdb->update(
			$table,
			array(
				'stock_qty'  => $new_qty,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $variant_id )
		);
		Simple_POS_Products::log_stock_change( $variant->parent_product_id, (int) $delta, $new_qty, $reason, $reference_id, get_current_user_id(), $note, $variant_id );
		return true;
	}

	public static function variant_label( $v ) {
		$attrs = maybe_unserialize( $v->attributes );
		if ( is_string( $attrs ) ) {
			$attrs = json_decode( $attrs, true );
		}
		if ( empty( $attrs ) || ! is_array( $attrs ) ) {
			return 'Variant #' . $v->id;
		}
		return implode(
			' / ',
			array_map(
				function ( $k, $val ) {
					return $k . ': ' . $val;
				},
				array_keys( $attrs ),
				$attrs
			)
		);
	}

	private static function sku_exists( $sku, $exclude = 0 ) {
		return Simple_POS_DB::sku_exists( $sku, 0, $exclude );
	}
	private static function barcode_exists( $code, $exclude = 0 ) {
		return Simple_POS_DB::barcode_exists( $code, 0, $exclude );
	}

	private static function sanitize( $data, $existing = null ) {
		$attrs = null;
		if ( isset( $data['attributes'] ) ) {
			if ( is_string( $data['attributes'] ) ) {
				$decoded = json_decode( $data['attributes'], true );
				$attrs   = $decoded !== null ? $decoded : array();
			} elseif ( is_array( $data['attributes'] ) ) {
				$attrs = $data['attributes'];
			}
			// sanitize keys/values
			$clean_attrs = array();
			foreach ( (array) $attrs as $k => $v ) {
				$k = sanitize_text_field( $k );
				$v = sanitize_text_field( $v );
				if ( $k !== '' && $v !== '' ) {
					$clean_attrs[ $k ] = $v;
				}
			}
			$attrs = ! empty( $clean_attrs ) ? wp_json_encode( $clean_attrs ) : null;
		} elseif ( $existing ) {
			$attrs = $existing->attributes;
		}
		$price = isset( $data['price'] ) && $data['price'] !== '' ? (float) $data['price'] : ( $existing->price ?? null );
		if ( $price !== null && $price < 0 ) {
			return new WP_Error( 'pos_invalid_input', __( 'Price cannot be negative.', 'simple-pos' ) );
		}
		$cost = isset( $data['cost_price'] ) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : ( $existing->cost_price ?? null );
		return array(
			'sku'                 => isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : ( $existing->sku ?? '' ),
			'barcode'             => isset( $data['barcode'] ) ? sanitize_text_field( $data['barcode'] ) : ( $existing->barcode ?? '' ),
			'price'               => $price,
			'cost_price'          => $cost,
			'stock_qty'           => isset( $data['stock_qty'] ) ? (int) $data['stock_qty'] : ( $existing->stock_qty ?? 0 ),
			'low_stock_threshold' => isset( $data['low_stock_threshold'] ) ? (int) $data['low_stock_threshold'] : ( $existing->low_stock_threshold ?? 5 ),
			'track_stock'         => isset( $data['track_stock'] ) ? ( $data['track_stock'] ? 1 : 0 ) : ( $existing->track_stock ?? 1 ),
			'image_url'           => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : ( $existing->image_url ?? '' ),
			'attributes'          => $attrs,
			'status'              => isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : ( $existing->status ?? 'active' ),
		);
	}
}
