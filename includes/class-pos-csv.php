<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_CSV {

	/**
	 * Escape CSV cells that start with formula characters to prevent injection.
	 * Prefixes with single quote to make Excel treat as text.
	 *
	 * @param mixed $value Cell value.
	 * @return mixed Escaped value.
	 */
	private static function escape_csv_formula( $value ) {
		if ( is_string( $value ) && strlen( $value ) > 0 ) {
			$first_char = substr( $value, 0, 1 );
			if ( in_array( $first_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				$value = "'" . $value;
			}
		}
		return $value;
	}

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
			fputcsv( $out, array( $p->id, self::escape_csv_formula( $p->name ), self::escape_csv_formula( $p->sku ), self::escape_csv_formula( $p->barcode ), $p->category_id, $p->price, $p->cost_price, $p->tax_class_id ?? '', $p->tax_rate, $p->stock_qty, $p->low_stock_threshold, $p->track_stock, self::escape_csv_formula( $p->image_url ), self::escape_csv_formula( $p->hsn_sac_code ?? '' ), $p->status ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );
		return $csv;
	}

	public static function import_products( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'wp-pos-plugin' ) );
		}
		// Validate file size (5MB limit).
		if ( filesize( $file_path ) > 5 * 1024 * 1024 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
			return new WP_Error( 'pos_file_too_large', __( 'File exceeds 5MB limit.', 'wp-pos-plugin' ) );
		}
		// Validate MIME type.
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );
		if ( ! in_array( $mime, array( 'text/csv', 'text/plain', 'application/csv' ), true ) ) {
			return new WP_Error( 'pos_invalid_file', __( 'Only CSV files are allowed.', 'wp-pos-plugin' ) );
		}
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'wp-pos-plugin' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'wp-pos-plugin' ) );}
		$header   = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'name', 'price' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle );
				/* translators: %s: missing CSV column name. */
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'wp-pos-plugin' ), $r ) ); }
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
			fputcsv( $out, array( self::escape_csv_formula( $s->sale_number ), $s->created_at, $s->customer_id, $s->cashier_id, $s->subtotal, $s->discount_amount, $s->tax_amount, $s->total, self::escape_csv_formula( $s->payment_method ), $s->status, self::escape_csv_formula( $s->tax_country ?? '' ), self::escape_csv_formula( $s->tax_state ?? '' ) ) );
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
			fputcsv( $out, array( $c->id, self::escape_csv_formula( $c->name ), self::escape_csv_formula( $c->description ?? '' ) ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	public static function import_categories( $file_path ) {
		if ( ! file_exists( $file_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'wp-pos-plugin' ) );
		}
		// Validate file size (5MB limit).
		if ( filesize( $file_path ) > 5 * 1024 * 1024 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
			return new WP_Error( 'pos_file_too_large', __( 'File exceeds 5MB limit.', 'wp-pos-plugin' ) );
		}
		// Validate MIME type.
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );
		if ( ! in_array( $mime, array( 'text/csv', 'text/plain', 'application/csv' ), true ) ) {
			return new WP_Error( 'pos_invalid_file', __( 'Only CSV files are allowed.', 'wp-pos-plugin' ) );
		}
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'wp-pos-plugin' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'wp-pos-plugin' ) );
		}
		$header   = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'name' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: missing column name */
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'wp-pos-plugin' ), $r ) );
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
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'wp-pos-plugin' ) );
		}
		// Validate file size (5MB limit).
		if ( filesize( $file_path ) > 5 * 1024 * 1024 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
			return new WP_Error( 'pos_file_too_large', __( 'File exceeds 5MB limit.', 'wp-pos-plugin' ) );
		}
		// Validate MIME type.
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );
		if ( ! in_array( $mime, array( 'text/csv', 'text/plain', 'application/csv' ), true ) ) {
			return new WP_Error( 'pos_invalid_file', __( 'Only CSV files are allowed.', 'wp-pos-plugin' ) );
		}
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'wp-pos-plugin' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'wp-pos-plugin' ) );
		}
		$header = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'sale_number', 'total', 'payment_method' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: missing column name */
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'wp-pos-plugin' ), $r ) );
			}
		}
		$imported = 0;
		$errors   = array();
		$rownum   = 1;
		global $wpdb;
		$sales_table = Simple_POS_DB::table( 'sales' );
		$items_table = Simple_POS_DB::table( 'sale_items' );
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
			// Insert sale directly (bypasses cart validation — this is raw data import).
			$inserted = $wpdb->insert(
				$sales_table,
				array(
					'sale_number'     => sanitize_text_field( $data['sale_number'] ),
					'customer_id'     => isset( $data['customer_id'] ) && $data['customer_id'] ? (int) $data['customer_id'] : null,
					'cashier_id'      => isset( $data['cashier_id'] ) ? (int) $data['cashier_id'] : 0,
					'subtotal'        => isset( $data['subtotal'] ) ? (float) $data['subtotal'] : 0,
					'discount_type'   => 'fixed',
					'discount_amount' => isset( $data['discount_amount'] ) ? (float) $data['discount_amount'] : 0,
					'tax_amount'      => isset( $data['tax_amount'] ) ? (float) $data['tax_amount'] : 0,
					'total'           => isset( $data['total'] ) ? (float) $data['total'] : 0,
					'amount_paid'     => isset( $data['total'] ) ? (float) $data['total'] : 0,
					'change_due'      => 0,
					'payment_method'  => sanitize_text_field( $data['payment_method'] ),
					'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'completed',
					'customer_type'   => 'b2c',
					'note'            => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '',
					'tax_country'     => isset( $data['tax_country'] ) ? sanitize_text_field( $data['tax_country'] ) : '',
					'tax_state'       => isset( $data['tax_state'] ) ? sanitize_text_field( $data['tax_state'] ) : '',
					'currency_code'   => isset( $data['currency_code'] ) ? sanitize_text_field( $data['currency_code'] ) : '',
					'exchange_rate'   => 1,
					'created_at'      => isset( $data['created_at'] ) ? sanitize_text_field( $data['created_at'] ) : current_time( 'mysql' ),
				)
			);
			if ( false === $inserted ) {
				$errors[] = "Row $rownum: " . __( 'Could not insert sale.', 'wp-pos-plugin' );
				continue;
			}
			$sale_id = (int) $wpdb->insert_id;
			// Insert line items if provided (product_id, qty, price columns).
			if ( isset( $data['product_id'] ) && $data['product_id'] && isset( $data['qty'] ) && $data['qty'] ) {
				$product = Simple_POS_Products::get_product( (int) $data['product_id'] );
				$product_name = $product ? $product->name : 'Imported item';
				$wpdb->insert(
					$items_table,
					array(
						'sale_id'      => $sale_id,
						'product_id'   => (int) $data['product_id'],
						'product_name' => $product_name,
						'sku'          => isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '',
						'qty'          => (int) $data['qty'],
						'price'        => isset( $data['price'] ) ? (float) $data['price'] : 0,
						'cost_price'   => isset( $data['cost_price'] ) ? (float) $data['cost_price'] : 0,
						'line_total'   => isset( $data['price'] ) ? (float) $data['price'] * (int) $data['qty'] : 0,
					)
				);
			}
			++$imported;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( $imported > 0 ) {
			Simple_POS_Reports::flush_cache();
		}
		return array(
			'imported' => $imported,
			'errors'   => $errors,
		);
	}

	/**
	 * Export product variants as CSV.
	 *
	 * @return string CSV content.
	 */
	public static function export_variants() {
		global $wpdb;
		$vt = Simple_POS_DB::table( 'product_variants' );
		$pt = Simple_POS_DB::table( 'products' );
		$all = $wpdb->get_results(
			"SELECT v.*, p.name as parent_name FROM {$vt} v INNER JOIN {$pt} p ON p.id = v.parent_product_id ORDER BY p.name, v.id" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$out = fopen( 'php://temp', 'r+' );
		fputcsv( $out, array( 'parent_product_id', 'parent_name', 'sku', 'barcode', 'price', 'cost_price', 'stock_qty', 'low_stock_threshold', 'track_stock', 'tax_class_id', 'image_url', 'hsn_sac_code', 'attributes', 'status' ) );
		foreach ( $all as $v ) {
			$attrs = '';
			if ( ! empty( $v->attributes ) ) {
				$parsed = json_decode( $v->attributes, true );
				if ( is_array( $parsed ) ) {
					$parts = array();
					foreach ( $parsed as $k => $val ) {
						$parts[] = $k . ':' . $val;
					}
					$attrs = implode( '; ', $parts );
				}
			}
			fputcsv( $out, array( $v->parent_product_id, self::escape_csv_formula( $v->parent_name ), self::escape_csv_formula( $v->sku ?? '' ), self::escape_csv_formula( $v->barcode ?? '' ), $v->price ?? '', $v->cost_price ?? '', $v->stock_qty, $v->low_stock_threshold, $v->track_stock, $v->tax_class_id ?? '', self::escape_csv_formula( $v->image_url ?? '' ), self::escape_csv_formula( $v->hsn_sac_code ?? '' ), self::escape_csv_formula( $attrs ), $v->status ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	/**
	 * Import product variants from CSV.
	 *
	 * CSV columns: parent_product_id (required), sku, barcode, price, cost_price,
	 * stock_qty, low_stock_threshold, track_stock, tax_class_id, image_url,
	 * hsn_sac_code, attributes (format: "Key:Value; Key2:Value2"), status.
	 *
	 * @param string $file_path Path to uploaded CSV file.
	 * @return array|WP_Error Results array or error.
	 */
	public static function import_variants( $file_path ) {
		if ( ! file_exists( $file_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
			return new WP_Error( 'pos_file_missing', __( 'CSV file missing.', 'wp-pos-plugin' ) );
		}
		// Validate file size (5MB limit).
		if ( filesize( $file_path ) > 5 * 1024 * 1024 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize
			return new WP_Error( 'pos_file_too_large', __( 'File exceeds 5MB limit.', 'wp-pos-plugin' ) );
		}
		// Validate MIME type.
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );
		if ( ! in_array( $mime, array( 'text/csv', 'text/plain', 'application/csv' ), true ) ) {
			return new WP_Error( 'pos_invalid_file', __( 'Only CSV files are allowed.', 'wp-pos-plugin' ) );
		}
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'pos_file_error', __( 'Could not open CSV.', 'wp-pos-plugin' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'pos_invalid_csv', __( 'Empty CSV.', 'wp-pos-plugin' ) );
		}
		$header   = array_map( 'strtolower', array_map( 'trim', $header ) );
		$required = array( 'parent_product_id' );
		foreach ( $required as $r ) {
			if ( ! in_array( $r, $header, true ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: missing column name */
				return new WP_Error( 'pos_invalid_csv', sprintf( __( 'Missing column: %s', 'wp-pos-plugin' ), $r ) );
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
			$parent_id = isset( $data['parent_product_id'] ) ? (int) $data['parent_product_id'] : 0;
			if ( $parent_id <= 0 ) {
				$errors[] = "Row $rownum: " . __( 'Invalid parent product ID.', 'wp-pos-plugin' );
				continue;
			}
			$parent = Simple_POS_Products::get_product( $parent_id );
			if ( ! $parent ) {
				/* translators: %d: parent product id. */
				$errors[] = "Row $rownum: " . sprintf( __( 'Parent product #%d not found.', 'wp-pos-plugin' ), $parent_id );
				continue;
			}
			// Parse attributes from "Key:Value; Key2:Value2" format.
			$attrs = array();
			if ( ! empty( $data['attributes'] ) ) {
				$pairs = array_map( 'trim', explode( ';', $data['attributes'] ) );
				foreach ( $pairs as $pair ) {
					if ( strpos( $pair, ':' ) !== false ) {
						list( $k, $v ) = array_map( 'trim', explode( ':', $pair, 2 ) );
						if ( '' !== $k && '' !== $v ) {
							$attrs[ $k ] = $v;
						}
					}
				}
			}
			$payload = array(
				'sku'                 => isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '',
				'barcode'             => isset( $data['barcode'] ) ? sanitize_text_field( $data['barcode'] ) : '',
				'price'               => isset( $data['price'] ) && '' !== $data['price'] ? (float) $data['price'] : null,
				'cost_price'          => isset( $data['cost_price'] ) && '' !== $data['cost_price'] ? (float) $data['cost_price'] : null,
				'stock_qty'           => isset( $data['stock_qty'] ) ? (int) $data['stock_qty'] : 0,
				'low_stock_threshold' => isset( $data['low_stock_threshold'] ) ? (int) $data['low_stock_threshold'] : 5,
				'track_stock'         => isset( $data['track_stock'] ) ? (int) $data['track_stock'] : 1,
				'tax_class_id'        => isset( $data['tax_class_id'] ) ? (int) $data['tax_class_id'] : 0,
				'image_url'           => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : '',
				'hsn_sac_code'        => isset( $data['hsn_sac_code'] ) ? sanitize_text_field( $data['hsn_sac_code'] ) : '',
				'attributes'          => $attrs,
				'status'              => isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			);
			$res = Simple_POS_Variants::create_variant( $parent_id, $payload );
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
