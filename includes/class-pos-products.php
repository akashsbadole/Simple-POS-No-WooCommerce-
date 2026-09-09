<?php
/**
 * Product & category data access.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Products {

	/**
	 * Fetch a paginated, filterable product list.
	 *
	 * @param array $args {
	 *     @type string $search    Search term (name, sku, barcode).
	 *     @type int    $category_id Filter by category.
	 *     @type string $status    active|inactive|any. Default active.
	 *     @type int    $per_page  Default 20.
	 *     @type int    $page      Default 1.
	 *     @type string $orderby   Column to order by. Default name.
	 *     @type string $order     ASC|DESC. Default ASC.
	 * }
	 * @return array { items: array, total: int }
	 */
	public static function get_products( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'      => '',
			'category_id' => 0,
			'status'      => 'active',
			'per_page'    => 20,
			'page'        => 1,
			'orderby'     => 'name',
			'order'       => 'ASC',
		);
		$args     = wp_parse_args( $args, $defaults );
		$table    = Simple_POS_DB::table( 'products' );

		$where  = array( '1=1' );
		$params = array();

		if ( 'any' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$params[] = (int) $args['category_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR sku LIKE %s OR barcode LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$allowed_orderby = array( 'name', 'price', 'stock_qty', 'created_at', 'sku' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$per_page = max( 1, min( Simple_POS_DB::MAX_PER_PAGE, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		// Total count (for pagination).
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Page of results.
		$sql          = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get a single product by ID.
	 *
	 * @param int $id Product ID.
	 * @return object|null
	 */
	public static function get_product( $id ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'products' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Find a product by exact SKU or barcode (used for barcode-scanner lookups).
	 * Searches products and variants. Variant match returns a merged object with variant_id.
	 *
	 * @param string $code SKU or barcode value.
	 * @return object|null
	 */
	public static function find_by_code( $code ) {
		global $wpdb;
		$pt      = Simple_POS_DB::table( 'products' );
		$vt      = Simple_POS_DB::table( 'product_variants' );
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$pt} WHERE (sku = %s OR barcode = %s) AND status = 'active' LIMIT 1",
				$code,
				$code
			)
		);
		if ( $product ) {
			$product->variant_id         = null;
			$product->variant_attributes = null;
			return $product;
		}
		$variant = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT v.*, p.name as parent_name, p.category_id, p.tax_rate, p.tax_class_id FROM {$vt} v INNER JOIN {$pt} p ON p.id=v.parent_product_id WHERE (v.sku = %s OR v.barcode = %s) AND v.status='active' LIMIT 1",
				$code,
				$code
			)
		);
		if ( $variant ) {
			// Build a product-like object for terminal: price from variant override or parent.
			$parent = self::get_product( $variant->parent_product_id );
			if ( ! $parent ) {
				return null;
			}
			$obj                     = clone $parent;
			$obj->variant_id         = (int) $variant->id;
			$obj->variant_attributes = $variant->attributes;
			$obj->sku                = $variant->sku ?: $parent->sku;
			$obj->barcode            = $variant->barcode ?: $parent->barcode;
			if ( null !== $variant->price ) {
				$obj->price = $variant->price;
			}
			if ( null !== $variant->cost_price ) {
				$obj->cost_price = $variant->cost_price;
			}
			$obj->stock_qty           = $variant->stock_qty;
			$obj->low_stock_threshold = $variant->low_stock_threshold;
			$obj->track_stock         = $variant->track_stock;
			$obj->image_url           = $variant->image_url ?: $parent->image_url;
			$obj->name                = $parent->name . ' — ' . Simple_POS_Variants::variant_label( $variant );
			// Keep parent tax class
			return $obj;
		}
		return null;
	}

	/**
	 * Fetch a product with its variants enriched (for API).
	 */
	public static function get_product_with_variants( $id ) {
		$product = self::get_product( $id );
		if ( ! $product ) {
			return null;
		}
		if ( class_exists( 'Simple_POS_Variants' ) ) {
			$product->variants = Simple_POS_Variants::get_variants( $id );
		}
		return $product;
	}

	/**
	 * Create a product.
	 *
	 * @param array $data Raw input.
	 * @return int|WP_Error New product ID or error.
	 */
	public static function create_product( $data ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'products' );

		$clean = self::sanitize_product_input( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( ! empty( $clean['sku'] ) && self::sku_exists( $clean['sku'] ) ) {
			return new WP_Error( 'pos_duplicate_sku', __( 'A product with this SKU already exists.', 'wp-pos-plugin' ) );
		}

		$clean['created_at'] = current_time( 'mysql' );
		$clean['updated_at'] = current_time( 'mysql' );

		$inserted = $wpdb->insert( $table, $clean ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $inserted ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create product.', 'wp-pos-plugin' ) );
		}

		$product_id = (int) $wpdb->insert_id;

		if ( (int) $clean['stock_qty'] > 0 ) {
			self::log_stock_change( $product_id, (int) $clean['stock_qty'], (int) $clean['stock_qty'], 'initial', null, get_current_user_id(), __( 'Initial stock on product creation', 'wp-pos-plugin' ) );
		}

		return $product_id;
	}

	/**
	 * Update a product.
	 *
	 * @param int   $id   Product ID.
	 * @param array $data Raw input.
	 * @return true|WP_Error
	 */
	public static function update_product( $id, $data ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'products' );

		$existing = self::get_product( $id );
		if ( ! $existing ) {
			return new WP_Error( 'pos_not_found', __( 'Product not found.', 'wp-pos-plugin' ) );
		}

		$clean = self::sanitize_product_input( $data, $existing );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( ! empty( $clean['sku'] ) && self::sku_exists( $clean['sku'], $id ) ) {
			return new WP_Error( 'pos_duplicate_sku', __( 'A product with this SKU already exists.', 'wp-pos-plugin' ) );
		}

		$old_qty = (int) $existing->stock_qty;
		$new_qty = (int) $clean['stock_qty'];

		$clean['updated_at'] = current_time( 'mysql' );

		$updated = $wpdb->update( $table, $clean, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $updated ) {
			return new WP_Error( 'pos_db_error', __( 'Could not update product.', 'wp-pos-plugin' ) );
		}

		if ( $new_qty !== $old_qty ) {
			self::log_stock_change( $id, $new_qty - $old_qty, $new_qty, 'adjustment', null, get_current_user_id(), __( 'Manual edit', 'wp-pos-plugin' ) );
		}

		return true;
	}

	/**
	 * Delete (soft-delete) a product by marking it inactive, unless it has
	 * no sale history in which case it is hard-deleted. Preserves sale_items
	 * referential integrity for reporting on historical sales.
	 * Cascades status to variants when soft-deleting.
	 *
	 * @param int $id Product ID.
	 * @return true|WP_Error
	 */
	public static function delete_product( $id ) {
		global $wpdb;
		$items_table    = Simple_POS_DB::table( 'sale_items' );
		$products_table = Simple_POS_DB::table( 'products' );
		$variants_table = Simple_POS_DB::table( 'product_variants' );

		$used_in_sales = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$items_table} WHERE product_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $used_in_sales > 0 ) {
			$wpdb->update( $products_table, array( 'status' => 'inactive' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			// Cascade inactive status to all variants of this product.
			$wpdb->update( $variants_table, array( 'status' => 'inactive' ), array( 'parent_product_id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return true;
		}

		// Hard delete: also remove all variants.
		$wpdb->delete( $variants_table, array( 'parent_product_id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$deleted = $wpdb->delete( $products_table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $deleted ) {
			return new WP_Error( 'pos_db_error', __( 'Could not delete product.', 'wp-pos-plugin' ) );
		}

		return true;
	}

	/**
	 * Products at or below their low-stock threshold (for admin alerts/reports).
	 * Includes variant low stock. Variants with track_stock=0 inherit from parent.
	 */
	public static function get_low_stock_products( $limit = 50 ) {
		global $wpdb;
		$pt       = Simple_POS_DB::table( 'products' );
		$vt       = Simple_POS_DB::table( 'product_variants' );
		$products = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pt} WHERE track_stock=1 AND status='active' AND stock_qty <= low_stock_threshold ORDER BY stock_qty ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$variants = $wpdb->get_results( $wpdb->prepare( "SELECT v.*, p.name as parent_name, p.stock_qty as parent_stock_qty, p.low_stock_threshold as parent_low_stock_threshold, p.track_stock as parent_track_stock FROM {$vt} v INNER JOIN {$pt} p ON p.id=v.parent_product_id WHERE v.status='active' AND ((v.track_stock=1 AND v.stock_qty <= v.low_stock_threshold) OR (v.track_stock=0 AND p.track_stock=1 AND p.stock_qty <= p.low_stock_threshold)) ORDER BY v.stock_qty ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// Merge and label variants.
		foreach ( $variants as $v ) {
			$v->name       = $v->parent_name . ' — ' . Simple_POS_Variants::variant_label( $v );
			$v->is_variant = 1;
		}
		$merged = array_merge( $products, $variants );
		usort(
			$merged,
			function ( $a, $b ) {
				return (int) $a->stock_qty - (int) $b->stock_qty;
			}
		);
		return array_slice( $merged, 0, $limit );
	}

	/**
	 * Adjust stock for a product by a relative amount and log it.
	 * If variant_id is provided, variant stock is adjusted instead of parent.
	 */
	public static function adjust_stock( $product_id, $delta, $reason = 'adjustment', $reference_id = null, $note = '', $variant_id = null ) {
		global $wpdb;
		
		// Validate inputs.
		if ( ! is_numeric( $delta ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Stock adjustment delta must be numeric.', 'wp-pos-plugin' ) );
		}
		
		$delta = (int) $delta;
		if ( $delta === 0 ) {
			return true; // No-op, but not an error.
		}
		
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return new WP_Error( 'pos_invalid_input', __( 'Invalid product ID.', 'wp-pos-plugin' ) );
		}
		
		if ( $variant_id && class_exists( 'Simple_POS_Variants' ) ) {
			return Simple_POS_Variants::adjust_stock( $variant_id, $delta, $reason, $reference_id, $note );
		}
		$table   = Simple_POS_DB::table( 'products' );
		$product = self::get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'pos_not_found', __( 'Product not found.', 'wp-pos-plugin' ) );
		}
		if ( ! $product->track_stock ) {
			return true;
		}
		$settings = Simple_POS_Settings::get_all();
		$new_qty  = (int) $product->stock_qty + (int) $delta;
		if ( $new_qty < 0 && empty( $settings['allow_negative_stock'] ) ) {
			/* translators: %s: product name. */
			return new WP_Error( 'pos_insufficient_stock', sprintf( __( 'Not enough stock for "%s".', 'wp-pos-plugin' ), $product->name ) );
		}
		$wpdb->update(
			$table,
			array(
				'stock_qty'  => $new_qty,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $product_id )
		);
		self::log_stock_change( $product_id, (int) $delta, $new_qty, $reason, $reference_id, get_current_user_id(), $note, $variant_id );
		return true;
	}

	/**
	 * Insert a row into the stock audit log.
	 */
	public static function log_stock_change( $product_id, $delta, $resulting_qty, $reason, $reference_id, $user_id, $note, $variant_id = null ) {
		global $wpdb;
		$table = Simple_POS_DB::table( 'stock_log' );
		$wpdb->insert(
			$table,
			array(
				'product_id'    => $product_id,
				'variant_id'    => $variant_id,
				'change_qty'    => $delta,
				'resulting_qty' => $resulting_qty,
				'reason'        => $reason,
				'reference_id'  => $reference_id,
				'user_id'       => $user_id ? $user_id : null,
				'note'          => $note,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Check if a SKU is already used by another product or variant.
	 * Delegates to Simple_POS_DB::sku_exists() for single source of truth.
	 */
	private static function sku_exists( $sku, $exclude_id = 0 ) {
		return Simple_POS_DB::sku_exists( $sku, $exclude_id, 0 );
	}
	private static function barcode_exists( $barcode, $exclude_id = 0 ) {
		return Simple_POS_DB::barcode_exists( $barcode, $exclude_id, 0 );
	}

	/**
	 * Validate and sanitize raw product input.
	 *
	 * @param array       $data     Raw input.
	 * @param object|null $existing Existing row, for partial updates.
	 * @return array|WP_Error
	 */
	private static function sanitize_product_input( $data, $existing = null ) {
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : ( $existing->name ?? '' );
		if ( empty( $name ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Product name is required.', 'wp-pos-plugin' ) );
		}

		$price = isset( $data['price'] ) ? (float) $data['price'] : ( $existing->price ?? 0 );
		if ( $price < 0 ) {
			return new WP_Error( 'pos_invalid_input', __( 'Price cannot be negative.', 'wp-pos-plugin' ) );
		}

		$tax_class_id = isset( $data['tax_class_id'] ) ? (int) $data['tax_class_id'] : ( $existing->tax_class_id ?? 0 );
		if ( ! $tax_class_id ) {
			$default_class = (int) Simple_POS_Settings::get( 'default_tax_class_id', 0 );
			if ( $default_class ) {
				$tax_class_id = $default_class;
			} else {
				global $wpdb;
				$std          = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Simple_POS_DB::table( 'tax_classes' ) . ' WHERE slug=%s', 'standard' ) );
				$tax_class_id = $std ? (int) $std : 0;
			}
		}
		if ( ! empty( $data['barcode'] ) && self::barcode_exists( sanitize_text_field( $data['barcode'] ), $existing->id ?? 0 ) ) {
			return new WP_Error( 'pos_duplicate_barcode', __( 'Barcode already exists.', 'wp-pos-plugin' ) );
		}
		return array(
			'name'                => $name,
			'category_id'         => isset( $data['category_id'] ) ? ( (int) $data['category_id'] ?: null ) : ( $existing->category_id ?? null ),
			'sku'                 => isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : ( $existing->sku ?? '' ),
			'barcode'             => isset( $data['barcode'] ) ? sanitize_text_field( $data['barcode'] ) : ( $existing->barcode ?? '' ),
			'price'               => $price,
			'cost_price'          => isset( $data['cost_price'] ) ? (float) $data['cost_price'] : ( $existing->cost_price ?? 0 ),
			'tax_rate'            => isset( $data['tax_rate'] ) ? (float) $data['tax_rate'] : ( $existing->tax_rate ?? 0 ),
			'tax_class_id'        => $tax_class_id,
			'stock_qty'           => isset( $data['stock_qty'] ) ? (int) $data['stock_qty'] : ( $existing->stock_qty ?? 0 ),
			'low_stock_threshold' => isset( $data['low_stock_threshold'] ) ? (int) $data['low_stock_threshold'] : ( $existing->low_stock_threshold ?? 5 ),
			'track_stock'         => isset( $data['track_stock'] ) ? ( $data['track_stock'] ? 1 : 0 ) : ( $existing->track_stock ?? 1 ),
		'image_url'           => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : ( $existing->image_url ?? '' ),
		'hsn_sac_code'        => isset( $data['hsn_sac_code'] ) ? sanitize_text_field( $data['hsn_sac_code'] ) : ( $existing->hsn_sac_code ?? '' ),
		'status'              => isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : ( $existing->status ?? 'active' ),
		);
	}

	/*
	---------------------------------------------------------------
	 * Categories
	 * ------------------------------------------------------------- */

	/**
	 * Get all categories.
	 *
	 * @return array
	 */
	public static function get_categories() {
		global $wpdb;
		$table = Simple_POS_DB::table( 'categories' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Create a category.
	 *
	 * @param string $name        Category name.
	 * @param string $description Optional description.
	 * @return int|WP_Error
	 */
	public static function create_category( $name, $description = '' ) {
		global $wpdb;
		$name = sanitize_text_field( $name );
		if ( empty( $name ) ) {
			return new WP_Error( 'pos_invalid_input', __( 'Category name is required.', 'wp-pos-plugin' ) );
		}

		$table = Simple_POS_DB::table( 'categories' );
		$slug  = sanitize_title( $name );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $description ),
				'created_at'  => current_time( 'mysql' ),
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'pos_db_error', __( 'Could not create category (name may already exist).', 'wp-pos-plugin' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a category. Products in it are reassigned to "Uncategorized"
	 * rather than being set to NULL.
	 *
	 * @param int $id Category ID.
	 * @return true
	 */
	public static function delete_category( $id ) {
		global $wpdb;
		$products_table   = Simple_POS_DB::table( 'products' );
		$categories_table = Simple_POS_DB::table( 'categories' );

		// Get or create "Uncategorized" default category.
		$uncategorized_id = self::get_or_create_uncategorized();

		$wpdb->update( $products_table, array( 'category_id' => $uncategorized_id ), array( 'category_id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->delete( $categories_table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return true;
	}

	/**
	 * Get the ID of the "Uncategorized" category, creating it if it doesn't exist.
	 *
	 * @return int Category ID.
	 */
	private static function get_or_create_uncategorized() {
		global $wpdb;
		$table = Simple_POS_DB::table( 'categories' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", 'uncategorized' ) );
		if ( $id ) {
			return $id;
		}
		return self::create_category( 'Uncategorized', 'Default category for unassigned products' );
	}
}
