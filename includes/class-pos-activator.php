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
			image_url VARCHAR(500) NULL,
			attributes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY parent_product_id (parent_product_id),
			KEY sku (sku),
			KEY barcode (barcode),
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
			note TEXT NULL,
			tax_country VARCHAR(10) NULL,
			tax_state VARCHAR(50) NULL,
			tax_breakdown TEXT NULL,
			currency_code VARCHAR(10) NULL,
			exchange_rate DECIMAL(12,6) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY sale_number (sale_number),
			KEY customer_id (customer_id),
			KEY cashier_id (cashier_id),
			KEY status (status),
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

		self::seed_tax_data();
		self::migrate_legacy_tax_rates();
	}

	private static function seed_tax_data() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		// Only seed once.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}tax_classes" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			$wpdb->insert(
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
			// US: 0 default, states override via separate rates if needed; keep national 0.
			array( $std, 'US', null, 0, 0, 0, 0, 'US Federal' ),
			array( $std, 'US', 'CA', 7.25, 0, 0, 1, 'California' ),
			array( $std, 'US', 'NY', 8.0, 0, 0, 1, 'New York' ),
			array( $std, 'US', 'TX', 6.25, 0, 0, 1, 'Texas' ),
			array( $std, 'US', 'FL', 6.0, 0, 0, 1, 'Florida' ),
			// India GST slabs — single national rate (states use same, avoid double-count; add state overrides only if different)
			array( $std, 'IN', null, 18.0, 0, 0, 0, 'India GST 18%' ),
			// Europe VAT examples
			array( $std, 'DE', null, 19.0, 0, 0, 0, 'Germany VAT' ),
			array( $std, 'FR', null, 20.0, 0, 0, 0, 'France VAT' ),
			array( $std, 'GB', null, 20.0, 0, 0, 0, 'UK VAT' ),
			array( $std, 'IT', null, 22.0, 0, 0, 0, 'Italy VAT' ),
			array( $std, 'ES', null, 21.0, 0, 0, 0, 'Spain VAT' ),
			array( $std, 'NL', null, 21.0, 0, 0, 0, 'Netherlands VAT' ),
			// Reduced
			array( $class_ids['reduced'], 'IN', null, 5.0, 0, 0, 0, 'India GST 5%' ),
			array( $class_ids['reduced'], 'DE', null, 7.0, 0, 0, 0, 'Germany reduced' ),
			array( $class_ids['reduced'], 'FR', null, 5.5, 0, 0, 0, 'France reduced' ),
			array( $class_ids['reduced'], 'GB', null, 5.0, 0, 0, 0, 'UK reduced' ),
			// Zero / Exempt
			array( $class_ids['zero'], '*', null, 0, 0, 0, 0, 'Zero' ),
			array( $class_ids['exempt'], '*', null, 0, 0, 0, 0, 'Exempt' ),
		);
		foreach ( $seed_rates as $r ) {
			$wpdb->insert(
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
					'created_at'   => $now,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	private static function migrate_legacy_tax_rates() {
		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		// If products still use legacy tax_rate and tax_class_id is all NULL, create mapping.
		$has_class = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}products WHERE tax_class_id IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $has_class > 0 ) {
			return;
		}
		$std_id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$prefix}tax_classes WHERE slug = %s", 'standard' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$zero_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$prefix}tax_classes WHERE slug = %s", 'zero' ) );
		if ( ! $std_id ) {
			return;
		}
		// Assign all products with tax_rate>0 to standard, 0 to zero.
		$wpdb->query( $wpdb->prepare( "UPDATE {$prefix}products SET tax_class_id = %d WHERE tax_rate > 0 AND tax_class_id IS NULL", $std_id ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$prefix}products SET tax_class_id = %d WHERE (tax_rate = 0 OR tax_rate IS NULL) AND tax_class_id IS NULL", $zero_id ?: $std_id ) );
		// For any remaining NULL assign standard.
		$wpdb->query( $wpdb->prepare( "UPDATE {$prefix}products SET tax_class_id = %d WHERE tax_class_id IS NULL", $std_id ) );
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
			'barcode_symbology'    => 'CODE128',
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
}
