<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_CSV {

	public static function export_products() {
		$all = Simple_POS_Products::get_products(
			array(
				'per_page' => 10000,
				'page'     => 1,
				'status'   => 'any',
			)
		);
		$out = fopen( 'php://temp', 'r+' );
		fputcsv( $out, array( 'id', 'name', 'sku', 'barcode', 'category_id', 'price', 'cost_price', 'tax_class_id', 'tax_rate', 'stock_qty', 'low_stock_threshold', 'track_stock', 'image_url', 'hsn_sac_code', 'status' ) );
		foreach ( $all['items'] as $p ) {
			fputcsv( $out, array( $p->id, $p->name, $p->sku, $p->barcode, $p->category_id, $p->price, $p->cost_price, $p->tax_class_id ?? '', $p->tax_rate, $p->stock_qty, $p->low_stock_threshold, $p->track_stock, $p->image_url, $p->hsn_sac_code ?? '', $p->status ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );
		return $csv;
	}

	public static function import_products( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'simple-pos' ) );
		}
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'simple-pos' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'simple-pos' ) );}
		$header   = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'name', 'price' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle );
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'simple-pos' ), $r ) ); }
		}
		$imported = 0;
		$errors   = array();
		$rownum   = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$rownum;
			$data = array_combine( $header, $row );
			if ( ! $data ) {
				continue;
			}
			$data = array_map( 'trim', $data );
			if ( empty( $data['name'] ) ) {
				continue;
			}
			// Map
			$payload = array(
				'name'                => $data['name'],
				'sku'                 => $data['sku'] ?? '',
				'barcode'             => $data['barcode'] ?? '',
				'category_id'         => isset( $data['category_id'] ) ? (int) $data['category_id'] : 0,
				'price'               => $data['price'] ?? 0,
				'cost_price'          => $data['cost_price'] ?? 0,
				'tax_class_id'        => $data['tax_class_id'] ?? 0,
				'tax_rate'            => $data['tax_rate'] ?? 0,
				'stock_qty'           => $data['stock_qty'] ?? 0,
				'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
				'track_stock'         => isset( $data['track_stock'] ) ? (int) $data['track_stock'] : 1,
			'image_url'           => $data['image_url'] ?? '',
			'hsn_sac_code'        => $data['hsn_sac_code'] ?? '',
			'status'              => $data['status'] ?? 'active',
			);
			// Upsert by SKU if id not provided
			$res = null;
			if ( ! empty( $data['id'] ) ) {
				$existing = Simple_POS_Products::get_product( (int) $data['id'] );
				$res      = $existing ? Simple_POS_Products::update_product( (int) $data['id'], $payload ) : Simple_POS_Products::create_product( $payload );
				if ( is_wp_error( $res ) ) {
					$errors[] = "Row $rownum: " . $res->get_error_message();
				} else {
					++$imported;
				}
			} else {
				// check duplicate sku
				if ( ! empty( $payload['sku'] ) ) {
					global $wpdb;
					$pt    = Simple_POS_DB::table( 'products' );
					$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pt} WHERE sku=%s", $payload['sku'] ) );
					if ( $found ) {
						$res = Simple_POS_Products::update_product( (int) $found, $payload );
					} else {
						$res = Simple_POS_Products::create_product( $payload );
						if ( ! is_wp_error( $res ) ) {
							$res = true;
						}
					}
				} else {
					$res = Simple_POS_Products::create_product( $payload );
					if ( ! is_wp_error( $res ) ) {
						$res = true;
					}
				}
				if ( is_wp_error( $res ) ) {
					$errors[] = "Row $rownum: " . $res->get_error_message();
				} else {
					++$imported;
				}
			}
		}
		fclose( $handle );
		return array(
			'imported' => $imported,
			'errors'   => $errors,
		);
	}

	public static function export_sales( $date_from = '', $date_to = '' ) {
		$args = array(
			'per_page' => 10000,
			'page'     => 1,
		);
		if ( $date_from ) {
			$args['date_from'] = $date_from;
		}
		if ( $date_to ) {
			$args['date_to'] = $date_to;
		}
		$res = Simple_POS_Sales::get_sales( $args );
		$out = fopen( 'php://temp', 'r+' );
		fputcsv( $out, array( 'sale_number', 'created_at', 'customer_id', 'cashier_id', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'payment_method', 'status', 'tax_country', 'tax_state' ) );
		foreach ( $res['items'] as $s ) {
			fputcsv( $out, array( $s->sale_number, $s->created_at, $s->customer_id, $s->cashier_id, $s->subtotal, $s->discount_amount, $s->tax_amount, $s->total, $s->payment_method, $s->status, $s->tax_country ?? '', $s->tax_state ?? '' ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	public static function export_categories() {
		$cats = Simple_POS_Products::get_categories();
		$out  = fopen( 'php://temp', 'r+' );
		fputcsv( $out, array( 'id', 'name', 'description' ) );
		foreach ( $cats as $c ) {
			fputcsv( $out, array( $c->id, $c->name, $c->description ?? '' ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	public static function import_categories( $file_path ) {
		if ( ! file_exists( $file_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'simple-pos' ) );
		}
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'simple-pos' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'simple-pos' ) );
		}
		$header   = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'name' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: missing column name */
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'simple-pos' ), $r ) );
			}
		}
		$imported = 0;
		$errors   = array();
		$rownum   = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$rownum;
			$data = array_combine( $header, $row );
			if ( ! $data ) {
				continue;
			}
			$data = array_map( 'trim', $data );
			if ( empty( $data['name'] ) ) {
				continue;
			}
			$name = sanitize_text_field( $data['name'] );
			$desc = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
			$res = Simple_POS_Products::create_category( $name, $desc );
			if ( is_wp_error( $res ) ) {
				$errors[] = "Row $rownum: " . $res->get_error_message();
			} else {
				++$imported;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array(
			'imported' => $imported,
			'errors'   => $errors,
		);
	}

	public static function import_sales( $file_path ) {
		if ( ! file_exists( $file_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'simple-pos' ) );
		}
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'simple-pos' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'simple-pos' ) );
		}
		$header = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'sale_number', 'total', 'payment_method' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: missing column name */
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'simple-pos' ), $r ) );
			}
		}
		$imported = 0;
		$errors   = array();
		$rownum   = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$rownum;
			$data = array_combine( $header, $row );
			if ( ! $data ) {
				continue;
			}
			$data = array_map( 'trim', $data );
			if ( empty( $data['sale_number'] ) || empty( $data['total'] ) ) {
				continue;
			}
			$res = Simple_POS_Sales::create_sale(
				array(
					'sale_number'      => $data['sale_number'],
					'customer_id'      => isset( $data['customer_id'] ) ? (int) $data['customer_id'] : 0,
					'cashier_id'       => isset( $data['cashier_id'] ) ? (int) $data['cashier_id'] : 0,
					'subtotal'         => isset( $data['subtotal'] ) ? (float) $data['subtotal'] : 0,
					'discount_amount'  => isset( $data['discount_amount'] ) ? (float) $data['discount_amount'] : 0,
					'tax_amount'       => isset( $data['tax_amount'] ) ? (float) $data['tax_amount'] : 0,
					'total'            => isset( $data['total'] ) ? (float) $data['total'] : 0,
					'payment_method'   => sanitize_text_field( $data['payment_method'] ),
					'status'           => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'completed',
					'tax_country'      => isset( $data['tax_country'] ) ? sanitize_text_field( $data['tax_country'] ) : '',
					'tax_state'        => isset( $data['tax_state'] ) ? sanitize_text_field( $data['tax_state'] ) : '',
				)
			);
			if ( is_wp_error( $res ) ) {
				$errors[] = "Row $rownum: " . $res->get_error_message();
			} else {
				++$imported;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array(
			'imported' => $imported,
			'errors'   => $errors,
		);
	}
}
