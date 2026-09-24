<?php
/**
 * Fired during plugin activation: creates DB schema, default options, roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Activator {

	/**
	 * Runs on register_activation_hook.
	 */
	public static function activate() {
		self::create_tables();
		self::create_default_options();

		if ( class_exists( 'Simple_POS_Roles' ) ) {
			Simple_POS_Roles::add_roles_and_caps();
		} else {
			// Roles class file may not be loaded yet at this point in some load orders; include directly.
			require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-roles.php';
			Simple_POS_Roles::add_roles_and_caps();
		}

		update_option( 'simple_pos_db_version', SIMPLE_POS_DB_VERSION );

		// Flush rewrite rules in case future versions add custom endpoints.
		flush_rewrite_rules();
	}

	/**
	 * Create (or update, via dbDelta) all custom tables.
	 * Uses $wpdb->prefix + 'pos_' so tables sit alongside core WP tables,
	 * e.g. wp_pos_products, wp_pos_sales, wp_pos_sale_items.
	 *
	 * Custom tables (rather than post types/postmeta) are used deliberately:
	 * sales and stock-log rows can grow into the tens of thousands quickly,
	 * and postmeta's EAV structure does not index or scale well for that
	 * volume of transactional writes and range-queries (date filters, etc).
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Categories.
		$sql_categories = "CREATE TABLE {$prefix}categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		// Tax classes.
		$sql_tax_classes = "CREATE TABLE {$prefix}tax_classes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			description VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		// Tax rates per country/state per class.
		$sql_tax_rates = "CREATE TABLE {$prefix}tax_rates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			country_code VARCHAR(10) NOT NULL,
			state_code VARCHAR(50) NULL,
			rate DECIMAL(7,4) NOT NULL DEFAULT 0,
			is_compound TINYINT(1) NOT NULL DEFAULT 0,
			is_inclusive TINYINT(1) NOT NULL DEFAULT 0,
		priority INT NOT NULL DEFAULT 0,
		name VARCHAR(100) NULL,
		gst_split TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY class_id (class_id),
			KEY country_code (country_code),
			KEY state_code (state_code)
		) $charset_collate;";

		// Products.
		$sql_products = "CREATE TABLE {$prefix}products (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id BIGINT UNSIGNED NULL,
			name VARCHAR(191) NOT NULL,
			sku VARCHAR(100) NULL,
			barcode VARCHAR(100) NULL,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
			tax_class_id BIGINT UNSIGNED NULL,
			stock_qty INT NOT NULL DEFAULT 0,
			low_stock_threshold INT NOT NULL DEFAULT 5,
		track_stock TINYINT(1) NOT NULL DEFAULT 1,
		image_url VARCHAR(500) NULL,
		hsn_sac_code VARCHAR(50) NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY category_id (category_id),
			KEY tax_class_id (tax_class_id),
			KEY sku (sku),
			KEY barcode (barcode),
			KEY status (status),
			KEY name (name)
		) $charset_collate;";

		// Product variants — free-form attributes, each variant has its own SKU/barcode/price/stock/image.
		$sql_variants = "CREATE TABLE {$prefix}product_variants (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			parent_product_id BIGINT UNSIGNED NOT NULL,
			sku VARCHAR(100) NULL,
			barcode VARCHAR(100) NULL,
			price DECIMAL(12,2) NULL,
			cost_price DECIMAL(12,2) NULL,
			stock_qty INT NOT NULL DEFAULT 0,
			low_stock_threshold INT NOT NULL DEFAULT 5,
			track_stock TINYINT(1) NOT NULL DEFAULT 1,
		tax_class_id BIGINT UNSIGNED NULL,
		image_url VARCHAR(500) NULL,
		hsn_sac_code VARCHAR(50) NULL,
		attributes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY parent_product_id (parent_product_id),
			KEY sku (sku),
			KEY barcode (barcode),
			KEY tax_class_id (tax_class_id),
			KEY status (status)
		) $charset_collate;";

		// Customers.
		$sql_customers = "CREATE TABLE {$prefix}customers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NULL,
			email VARCHAR(191) NULL,
			address TEXT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY email (email)
		) $charset_collate;";

		// Sales (one row per transaction).
		$sql_sales = "CREATE TABLE {$prefix}sales (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sale_number VARCHAR(50) NOT NULL,
			customer_id BIGINT UNSIGNED NULL,
			cashier_id BIGINT UNSIGNED NOT NULL,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
			discount_type VARCHAR(10) NOT NULL DEFAULT 'fixed',
			discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
			change_due DECIMAL(12,2) NOT NULL DEFAULT 0,
		payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
		status VARCHAR(20) NOT NULL DEFAULT 'completed',
		customer_type VARCHAR(10) NOT NULL DEFAULT 'b2c',
		note TEXT NULL,
			tax_country VARCHAR(10) NULL,
			tax_state VARCHAR(50) NULL,
			tax_breakdown TEXT NULL,
			currency_code VARCHAR(10) NULL,
			exchange_rate DECIMAL(12,6) NULL,
			outlet_id BIGINT UNSIGNED NULL,
			table_id BIGINT UNSIGNED NULL,
			client_key VARCHAR(64) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY sale_number (sale_number),
			UNIQUE KEY client_key (client_key),
			KEY customer_id (customer_id),
			KEY cashier_id (cashier_id),
			KEY status (status),
			KEY outlet_id (outlet_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Sale line items.
		$sql_sale_items = "CREATE TABLE {$prefix}sale_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sale_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NULL,
			variant_id BIGINT UNSIGNED NULL,
			product_name VARCHAR(191) NOT NULL,
			sku VARCHAR(100) NULL,
			qty INT NOT NULL DEFAULT 1,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
		tax_class_id BIGINT UNSIGNED NULL,
		tax_breakdown TEXT NULL,
		tax_rate_applied DECIMAL(7,4) NULL,
		customer_type VARCHAR(10) NOT NULL DEFAULT 'b2c',
		line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY sale_id (sale_id),
			KEY product_id (product_id),
			KEY variant_id (variant_id)
		) $charset_collate;";

		// Stock movement log (audit trail for every stock change).
		$sql_stock_log = "CREATE TABLE {$prefix}stock_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			variant_id BIGINT UNSIGNED NULL,
			change_qty INT NOT NULL,
			resulting_qty INT NOT NULL,
			reason VARCHAR(30) NOT NULL DEFAULT 'adjustment',
			reference_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			note VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY variant_id (variant_id),
			KEY reason (reason),
			KEY created_at (created_at)
		) $charset_collate;";

		// Suppliers.
		$sql_suppliers = "CREATE TABLE {$prefix}suppliers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			contact_name VARCHAR(191) NULL,
			phone VARCHAR(50) NULL,
			email VARCHAR(191) NULL,
			address TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY name (name)
		) $charset_collate;";

		// Purchase orders.
		$sql_pos = "CREATE TABLE {$prefix}purchase_orders (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			po_number VARCHAR(50) NOT NULL,
			supplier_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			total_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
			note TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ordered_at DATETIME NULL,
			received_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			UNIQUE KEY po_number (po_number),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) $charset_collate;";

		$sql_po_items = "CREATE TABLE {$prefix}po_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			po_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NULL,
			variant_id BIGINT UNSIGNED NULL,
			qty INT NOT NULL DEFAULT 1,
			received_qty INT NOT NULL DEFAULT 0,
			cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY po_id (po_id),
			KEY product_id (product_id),
			KEY variant_id (variant_id)
		) $charset_collate;";

		// Sale number sequence table for atomic sale number generation.
		$sql_sale_sequences = "CREATE TABLE {$prefix}sale_sequences (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			PRIMARY KEY (id)
		) $charset_collate;";

		dbDelta( $sql_categories );
		dbDelta( $sql_tax_classes );
		dbDelta( $sql_tax_rates );
		dbDelta( $sql_products );
		dbDelta( $sql_variants );
		dbDelta( $sql_customers );
		dbDelta( $sql_sales );
		dbDelta( $sql_sale_items );
		dbDelta( $sql_stock_log );
		dbDelta( $sql_suppliers );
		dbDelta( $sql_pos );
		dbDelta( $sql_po_items );
		dbDelta( $sql_sale_sequences );

		self::seed_tax_data();
		self::migrate_legacy_tax_rates();
		self::migrate_add_variant_tax_class();
		self::migrate_add_hsn_sac_columns();
		self::migrate_add_gst_split_column();
		self::migrate_add_customer_type_columns();
		self::migrate_ensure_sale_sequences();
		self::migrate_resync_sale_sequences();
		self::migrate_ensure_sales_columns();
		self::migrate_add_foreign_keys();
		// NOTE: do NOT drop the legacy products.tax_rate column. Product
		// queries/CSV still reference it and dropping it breaks installs
		// where code and schema versions drift. It is harmless to keep.
	}

	private static function seed_tax_data() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		// Only seed once.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}tax_classes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}
		$now       = current_time( 'mysql' );
		$classes   = array(
			array( 'Standard Rate', 'standard', 'Default standard rate' ),
			array( 'Reduced Rate', 'reduced', 'Reduced rate (food, books, etc.)' ),
			array( 'Zero Rate', 'zero', 'Zero-rated goods' ),
			array( 'Exempt', 'exempt', 'Tax exempt' ),
		);
		$class_ids = array();
		foreach ( $classes as $c ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation seed insert.
				$prefix . 'tax_classes',
				array(
					'name'        => $c[0],
					'slug'        => $c[1],
					'description' => $c[2],
					'created_at'  => $now,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$class_ids[ $c[1] ] = (int) $wpdb->insert_id;
		}
		// Seed per-country rates for Standard class.
		$std        = $class_ids['standard'];
		$seed_rates = array(
			array( $std, 'US', null, 0, 0, 0, 0, 'US Federal', 0 ),
			array( $std, 'US', 'CA', 7.25, 0, 0, 1, 'California', 0 ),
			array( $std, 'US', 'NY', 8.0, 0, 0, 1, 'New York', 0 ),
			array( $std, 'US', 'TX', 6.25, 0, 0, 1, 'Texas', 0 ),
			array( $std, 'US', 'FL', 6.0, 0, 0, 1, 'Florida', 0 ),
			array( $std, 'IN', null, 18.0, 0, 1, 0, 'India GST 18%', 0 ),
			array( $std, 'IN', 'MH', 18.0, 0, 1, 0, 'Maharashtra GST', 1 ),
			array( $std, 'IN', 'DL', 18.0, 0, 1, 0, 'Delhi GST', 1 ),
			array( $std, 'DE', null, 19.0, 0, 1, 0, 'Germany VAT', 0 ),
			array( $std, 'FR', null, 20.0, 0, 1, 0, 'France VAT', 0 ),
			array( $std, 'GB', null, 20.0, 0, 1, 0, 'UK VAT', 0 ),
			array( $std, 'IT', null, 22.0, 0, 1, 0, 'Italy VAT', 0 ),
			array( $std, 'ES', null, 21.0, 0, 1, 0, 'Spain VAT', 0 ),
			array( $std, 'NL', null, 21.0, 0, 1, 0, 'Netherlands VAT', 0 ),
			array( $std, 'CA', null, 5.0, 0, 0, 0, 'Canada GST', 0 ),
			array( $std, 'CA', 'BC', 7.0, 1, 0, 1, 'BC PST', 0 ),
			array( $std, 'CA', 'QC', 9.975, 1, 0, 1, 'QC PST', 0 ),
			array( $class_ids['reduced'], 'IN', null, 5.0, 0, 1, 0, 'India GST 5%', 0 ),
			array( $class_ids['reduced'], 'DE', null, 7.0, 0, 1, 0, 'Germany reduced', 0 ),
			array( $class_ids['reduced'], 'FR', null, 5.5, 0, 1, 0, 'France reduced', 0 ),
			array( $class_ids['reduced'], 'GB', null, 5.0, 0, 1, 0, 'UK reduced', 0 ),
			array( $class_ids['zero'], '*', null, 0, 0, 0, 0, 'Zero', 0 ),
			array( $class_ids['exempt'], '*', null, 0, 0, 0, 0, 'Exempt', 0 ),
		);
		foreach ( $seed_rates as $r ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation seed insert.
				$prefix . 'tax_rates',
				array(
				'class_id'     => $r[0],
				'country_code' => $r[1],
				'state_code'   => $r[2],
				'rate'         => $r[3],
				'is_compound'  => $r[4],
				'is_inclusive' => $r[5],
				'priority'     => $r[6],
				'name'         => $r[7],
				'gst_split'    => isset( $r[8] ) ? (int) $r[8] : 0,
				'created_at'   => $now,
			)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	private static function migrate_legacy_tax_rates() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		// The legacy column may have been dropped on some installs — the
		// queries below reference it, so bail early when it is absent
		// (migrate_ensure_sales_columns() re-adds it further below).
		$col = $wpdb->get_col( "SHOW COLUMNS FROM `{$prefix}products` WHERE Field = 'tax_rate'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $col ) ) {
			return;
		}
		// If products still use legacy tax_rate and tax_class_id is all NULL, create mapping.
		$has_class = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}products WHERE tax_class_id IS NOT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $has_class > 0 ) {
			return;
		}
		$std_id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$prefix}tax_classes WHERE slug = %s", 'standard' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$zero_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$prefix}tax_classes WHERE slug = %s", 'zero' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation/migration routine; runs once via dbDelta/upgrades, no caching applicable.
		if ( ! $std_id ) {
			return;
		}
		// Assign all products with tax_rate>0 to standard, 0 to zero.
		$wpdb->query( $wpdb->prepare( "UPDATE {$prefix}products SET tax_class_id = %d WHERE tax_rate > 0 AND tax_class_id IS NULL", $std_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation/migration routine; runs once via dbDelta/upgrades, no caching applicable.
		$wpdb->query( $wpdb->prepare( "UPDATE {$prefix}products SET tax_class_id = %d WHERE (tax_rate = 0 OR tax_rate IS NULL) AND tax_class_id IS NULL", $zero_id ?: $std_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation/migration routine; runs once via dbDelta/upgrades, no caching applicable.
		// For any remaining NULL assign standard.
		$wpdb->query( $wpdb->prepare( "UPDATE {$prefix}products SET tax_class_id = %d WHERE tax_class_id IS NULL", $std_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Activation/migration routine; runs once via dbDelta/upgrades, no caching applicable.
	}

	private static function migrate_add_variant_tax_class() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$table  = $prefix . 'product_variants';
		$col    = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}` WHERE Field = 'tax_class_id'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $col ) ) {
			return;
		}
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN tax_class_id BIGINT UNSIGNED NULL AFTER track_stock, ADD KEY tax_class_id (tax_class_id)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function migrate_add_hsn_sac_columns() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$products = $prefix . 'products';
		$variants = $prefix . 'product_variants';
		$col = $wpdb->get_col( "SHOW COLUMNS FROM `{$products}` WHERE Field = 'hsn_sac_code'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE `{$products}` ADD COLUMN hsn_sac_code VARCHAR(50) NULL AFTER image_url" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$col = $wpdb->get_col( "SHOW COLUMNS FROM `{$variants}` WHERE Field = 'hsn_sac_code'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE `{$variants}` ADD COLUMN hsn_sac_code VARCHAR(50) NULL AFTER image_url" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	private static function migrate_add_gst_split_column() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$table  = $prefix . 'tax_rates';
		$col    = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}` WHERE Field = 'gst_split'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $col ) ) {
			return;
		}
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN gst_split TINYINT(1) NOT NULL DEFAULT 0 AFTER name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function migrate_add_customer_type_columns() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$sales = $prefix . 'sales';
		$items = $prefix . 'sale_items';
		$col = $wpdb->get_col( "SHOW COLUMNS FROM `{$sales}` WHERE Field = 'customer_type'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE `{$sales}` ADD COLUMN customer_type VARCHAR(10) NOT NULL DEFAULT 'b2c' AFTER status" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$col = $wpdb->get_col( "SHOW COLUMNS FROM `{$items}` WHERE Field = 'customer_type'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE `{$items}` ADD COLUMN customer_type VARCHAR(10) NOT NULL DEFAULT 'b2c' AFTER tax_rate_applied" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * migration: the sale_sequences table was introduced without a DB
	 * version bump, so installs already on 1.0.0 never created it — every
	 * checkout then reuses sale_number POS-000000 and the 2nd sale fails
	 * with "Could not record sale." (duplicate key). CREATE IF NOT EXISTS
	 * is safe to run on every upgrade.
	 */
	private static function migrate_ensure_sale_sequences() {
		global $wpdb;
		$prefix   = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$charset  = $wpdb->get_charset_collate();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$prefix}sale_sequences` ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id) ) {$charset}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Repair: fast-forward the sale_sequences counter past the highest
	 * sale_number suffix already stored, so next_sale_number() cannot
	 * hand out a duplicate (which fails checkout with "Could not record
	 * sale."). Imports, manual rows and rolled-back checkouts can leave
	 * the sequence behind sales; inserting an explicit id bumps the
	 * AUTO_INCREMENT counter without touching existing rows.
	 */
	private static function migrate_resync_sale_sequences() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$seq    = $prefix . 'sale_sequences';
		$sales  = $prefix . 'sales';

		$settings = get_option( 'simple_pos_settings', array() );
		$number_prefix = isset( $settings['sale_number_prefix'] ) ? (string) $settings['sale_number_prefix'] : 'POS-';

		$numbers = $wpdb->get_col( "SELECT sale_number FROM `{$sales}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $numbers ) ) {
			return;
		}
		$max = 0;
		foreach ( (array) $numbers as $n ) {
			$n = (string) $n;
			if ( '' !== $number_prefix && 0 !== strpos( $n, $number_prefix ) ) {
				continue;
			}
			if ( preg_match( '/(\d+)\s*$/', $n, $m ) ) {
				$max = max( $max, (int) $m[1] );
			}
		}
		if ( $max <= 0 ) {
			return;
		}
		$seq_max = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM `{$seq}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $seq_max >= $max ) {
			return;
		}
		// Explicit-id insert advances AUTO_INCREMENT to $max + 1. If the
		// id already exists the counter is already past it — ignore.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$seq}` (id) VALUES (%d)", $max ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * 2.1.1 repair: ensure every column that create_sale()/sale-items
	 * writes actually exists (upgrades that skipped versions may miss
	 * them and $wpdb->insert then fails with "Unknown column").
	 */
	private static function migrate_ensure_sales_columns() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$sales  = $prefix . 'sales';
		$items  = $prefix . 'sale_items';
		$want_sales = array(
			'customer_type'  => "ADD COLUMN customer_type VARCHAR(10) NOT NULL DEFAULT 'b2c'",
			'tax_country'    => 'ADD COLUMN tax_country VARCHAR(10) NULL',
			'tax_state'      => 'ADD COLUMN tax_state VARCHAR(50) NULL',
			'tax_breakdown'  => 'ADD COLUMN tax_breakdown TEXT NULL',
			'currency_code'  => 'ADD COLUMN currency_code VARCHAR(10) NULL',
			'exchange_rate'  => 'ADD COLUMN exchange_rate DECIMAL(12,6) NULL',
			'outlet_id'      => 'ADD COLUMN outlet_id BIGINT UNSIGNED NULL, ADD KEY outlet_id (outlet_id)',
			'table_id'       => 'ADD COLUMN table_id BIGINT UNSIGNED NULL',
			'client_key'     => 'ADD COLUMN client_key VARCHAR(64) NULL, ADD UNIQUE KEY client_key (client_key)',
		);
		$want_items = array(
			'tax_class_id'     => 'ADD COLUMN tax_class_id BIGINT UNSIGNED NULL',
			'tax_breakdown'    => 'ADD COLUMN tax_breakdown TEXT NULL',
			'tax_rate_applied' => 'ADD COLUMN tax_rate_applied DECIMAL(7,4) NULL',
			'customer_type'    => "ADD COLUMN customer_type VARCHAR(10) NOT NULL DEFAULT 'b2c'",
			'line_total'       => 'ADD COLUMN line_total DECIMAL(12,2) NOT NULL DEFAULT 0',
		);
		foreach ( $want_sales as $col => $ddl ) {
			$exists = $wpdb->get_col( "SHOW COLUMNS FROM `{$sales}` WHERE Field = '{$col}'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( empty( $exists ) ) {
				$wpdb->query( "ALTER TABLE `{$sales}` {$ddl}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		foreach ( $want_items as $col => $ddl ) {
			$exists = $wpdb->get_col( "SHOW COLUMNS FROM `{$items}` WHERE Field = '{$col}'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( empty( $exists ) ) {
				$wpdb->query( "ALTER TABLE `{$items}` {$ddl}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		// Keep legacy products.tax_rate (see note above) — re-add if a
		// previous version dropped it, so product SELECTs keep working.
		$products = $prefix . 'products';
		$exists   = $wpdb->get_col( "SHOW COLUMNS FROM `{$products}` WHERE Field = 'tax_rate'", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $exists ) ) {
			$wpdb->query( "ALTER TABLE `{$products}` ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER cost_price" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Seed default plugin settings (only if not already set).
	 */
	private static function create_default_options() {
		$defaults = array(
			'currency_code'        => 'USD',
			'currency_symbol'      => '$',
			'currency_position'    => 'before', // before | after
			'default_tax_rate'     => 0,
			'default_tax_class_id' => 0,
			'tax_country'          => 'US',
			'tax_state'            => '',
			'tax_inclusive'        => 0,
			'discount_before_tax'  => 1,
			'tax_rounding'         => 'line', // line | total
			'store_name'           => get_bloginfo( 'name' ),
			'receipt_header'       => '',
			'receipt_footer'       => 'Thank you for your purchase!',
			'low_stock_threshold'  => 5,
			'sale_number_prefix'   => 'POS-',
			'po_number_prefix'     => 'PO-',
			'allow_negative_stock' => 0,
			'decimal_places'       => 2,
			'paper_width'          => '80mm',
			'auto_kick_drawer'     => 0,
			'printer_type'         => 'browser', // browser | usb | network
			'network_printer_ip'   => '',
			'barcode_symbology'    => 'CODE39',
			'barcode_label_format' => 'a4_30',
		);

		if ( false === get_option( 'simple_pos_settings' ) ) {
			add_option( 'simple_pos_settings', $defaults, '', 'yes' );
		} else {
			// Merge new defaults for existing installs.
			$existing = get_option( 'simple_pos_settings', array() );
			$merged   = wp_parse_args( $existing, $defaults );
			if ( $merged !== $existing ) {
				update_option( 'simple_pos_settings', $merged );
			}
		}
	}

	/**
	 * Add foreign key constraints to enforce referential integrity.
	 * Runs only on InnoDB tables; silently skips on MyISAM.
	 */
	private static function migrate_add_foreign_keys() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;

		// Check if foreign keys already exist.
		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND TABLE_NAME = '{$prefix}sale_items'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $existing > 0 ) {
			return;
		}

		// Verify InnoDB engine before adding FKs.
		$engine = $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}sales'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'InnoDB' !== $engine ) {
			return;
		}

		// Add foreign keys (use separate queries to handle partial failures).
		$wpdb->query( "ALTER TABLE `{$prefix}sale_items` ADD CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES `{$prefix}sales`(id) ON DELETE CASCADE" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$prefix}sale_items` ADD CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES `{$prefix}products`(id) ON DELETE SET NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$prefix}product_variants` ADD CONSTRAINT fk_variants_product FOREIGN KEY (parent_product_id) REFERENCES `{$prefix}products`(id) ON DELETE CASCADE" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$prefix}stock_log` ADD CONSTRAINT fk_stock_log_product FOREIGN KEY (product_id) REFERENCES `{$prefix}products`(id) ON DELETE CASCADE" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$prefix}po_items` ADD CONSTRAINT fk_po_items_po FOREIGN KEY (po_id) REFERENCES `{$prefix}purchase_orders`(id) ON DELETE CASCADE" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// NOTE: the legacy products.tax_rate column is intentionally never
	// dropped (an earlier version did, which broke product reads on
	// installs whose code expected it). migrate_ensure_sales_columns()
	// re-adds it if missing; nothing here may remove it.
}
