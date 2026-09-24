<?php
/**
 * wp-admin integration: menus, asset loading, settings handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Admin {

	/**
	 * Hook everything up. Called from the main plugin bootstrap.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		// Settings is registered late so it stays the last submenu item,
		// even when add-ons (e.g. Auto-PO) append their own pages.
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_action( 'admin_post_simple_pos_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_simple_pos_save_tax_class', array( __CLASS__, 'handle_save_tax_class' ) );
		add_action( 'admin_post_simple_pos_delete_tax_class', array( __CLASS__, 'handle_delete_tax_class' ) );
		add_action( 'admin_post_simple_pos_save_tax_rate', array( __CLASS__, 'handle_save_tax_rate' ) );
		add_action( 'admin_post_simple_pos_delete_tax_rate', array( __CLASS__, 'handle_delete_tax_rate' ) );
		add_action( 'admin_post_simple_pos_save_product', array( __CLASS__, 'handle_save_product' ) );
		add_action( 'admin_post_simple_pos_delete_product', array( __CLASS__, 'handle_delete_product' ) );
		add_action( 'admin_post_simple_pos_adjust_stock', array( __CLASS__, 'handle_adjust_stock' ) );
		add_action( 'admin_post_simple_pos_save_variant', array( __CLASS__, 'handle_save_variant' ) );
		add_action( 'admin_post_simple_pos_delete_variant', array( __CLASS__, 'handle_delete_variant' ) );
		add_action( 'admin_post_simple_pos_save_category', array( __CLASS__, 'handle_save_category' ) );
		add_action( 'admin_post_simple_pos_delete_category', array( __CLASS__, 'handle_delete_category' ) );
		add_action( 'admin_post_simple_pos_save_customer', array( __CLASS__, 'handle_save_customer' ) );
		add_action( 'admin_post_simple_pos_delete_customer', array( __CLASS__, 'handle_delete_customer' ) );
		add_action( 'admin_post_simple_pos_void_sale', array( __CLASS__, 'handle_void_sale' ) );
		add_action( 'admin_post_simple_pos_save_supplier', array( __CLASS__, 'handle_save_supplier' ) );
		add_action( 'admin_post_simple_pos_delete_supplier', array( __CLASS__, 'handle_delete_supplier' ) );
		add_action( 'admin_post_simple_pos_save_po', array( __CLASS__, 'handle_save_po' ) );
		add_action( 'admin_post_simple_pos_receive_po', array( __CLASS__, 'handle_receive_po' ) );
		add_action( 'admin_post_simple_pos_delete_po', array( __CLASS__, 'handle_delete_po' ) );
		add_action( 'admin_post_simple_pos_import_products', array( __CLASS__, 'handle_import_products' ) );
		add_action( 'admin_post_simple_pos_export_products', array( __CLASS__, 'handle_export_products' ) );
		add_action( 'admin_post_simple_pos_import_variants', array( __CLASS__, 'handle_import_variants' ) );
		add_action( 'admin_post_simple_pos_export_variants', array( __CLASS__, 'handle_export_variants' ) );
		add_action( 'admin_post_simple_pos_export_sales', array( __CLASS__, 'handle_export_sales' ) );
		add_action( 'admin_post_simple_pos_import_sales', array( __CLASS__, 'handle_import_sales' ) );
		add_action( 'admin_post_simple_pos_export_categories', array( __CLASS__, 'handle_export_categories' ) );
		add_action( 'admin_post_simple_pos_import_categories', array( __CLASS__, 'handle_import_categories' ) );
		add_action( 'admin_post_simple_pos_backup_export', array( __CLASS__, 'handle_backup_export' ) );
		add_action( 'admin_post_simple_pos_backup_import', array( __CLASS__, 'handle_backup_import' ) );
		add_action( 'admin_post_simple_pos_addon_toggle', array( __CLASS__, 'handle_addon_toggle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'low_stock_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'action_result_notice' ) );
		add_action( 'admin_notices', array( 'Simple_POS_Addons', 'show_compatibility_notices' ) );
		add_shortcode( 'simple_pos_terminal', array( __CLASS__, 'render_shortcode_terminal' ) );
	}

	/**
	 * Register the plugin's admin menu and submenus.
	 * Each submenu is gated by the capability relevant to that screen so
	 * cashiers only ever see the Terminal (and Sales History, read-only).
	 */
	public static function register_menus() {
		add_menu_page(
			__( 'POS', 'simple-pos' ),
			__( 'POS', 'simple-pos' ),
			'operate_pos',
			'simple-pos-terminal',
			array( __CLASS__, 'render_terminal_page' ),
			'dashicons-cart',
			25
		);

		add_submenu_page( 'simple-pos-terminal', __( 'Terminal', 'simple-pos' ), __( 'Terminal', 'simple-pos' ), 'operate_pos', 'simple-pos-terminal', array( __CLASS__, 'render_terminal_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Dashboard', 'simple-pos' ), __( 'Dashboard', 'simple-pos' ), 'view_pos_reports', 'simple-pos-dashboard', array( __CLASS__, 'render_dashboard_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Products', 'simple-pos' ), __( 'Products', 'simple-pos' ), 'view_pos_products', 'simple-pos-products', array( __CLASS__, 'render_products_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Customers', 'simple-pos' ), __( 'Customers', 'simple-pos' ), 'operate_pos', 'simple-pos-customers', array( __CLASS__, 'render_customers_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Sales History', 'simple-pos' ), __( 'Sales History', 'simple-pos' ), 'view_pos_sales', 'simple-pos-sales', array( __CLASS__, 'render_sales_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Reports', 'simple-pos' ), __( 'Reports', 'simple-pos' ), 'view_pos_reports', 'simple-pos-reports', array( __CLASS__, 'render_reports_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Taxes', 'simple-pos' ), __( 'Taxes', 'simple-pos' ), 'manage_pos_settings', 'simple-pos-taxes', array( __CLASS__, 'render_taxes_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Suppliers', 'simple-pos' ), __( 'Suppliers', 'simple-pos' ), 'manage_pos_products', 'simple-pos-suppliers', array( __CLASS__, 'render_suppliers_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Purchase Orders', 'simple-pos' ), __( 'Purchase Orders', 'simple-pos' ), 'manage_pos_products', 'simple-pos-purchase-orders', array( __CLASS__, 'render_purchase_orders_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Barcode Labels', 'simple-pos' ), __( 'Barcode Labels', 'simple-pos' ), 'manage_pos_products', 'simple-pos-barcode', array( __CLASS__, 'render_barcode_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Backup', 'simple-pos' ), __( 'Backup', 'simple-pos' ), 'manage_pos_settings', 'simple-pos-backup', array( __CLASS__, 'render_backup_page' ) );
		add_submenu_page( 'simple-pos-terminal', __( 'Add-ons', 'simple-pos' ), __( 'Add-ons', 'simple-pos' ), 'manage_pos_settings', 'simple-pos-addons', array( __CLASS__, 'render_addons_page' ) );
	}

	/**
	 * Register the Settings submenu last (see init() — hooked at a late
	 * priority so add-on pages always land above it).
	 */
	public static function register_settings_menu() {
		add_submenu_page( 'simple-pos-terminal', __( 'Settings', 'simple-pos' ), __( 'Settings', 'simple-pos' ), 'manage_pos_settings', 'simple-pos-settings', array( __CLASS__, 'render_settings_page' ) );
	}

	/**
	 * Enqueue CSS/JS only on this plugin's own admin screens — keeps the
	 * rest of wp-admin fast and avoids script collisions elsewhere.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		// $hook values look like "toplevel_page_simple-pos-terminal" and
		// "simple-pos-terminal_page_simple-pos-products" — they contain
		// "simple-pos", never "simple-pos" (that's only the text domain).
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_pos_page = ( false !== strpos( (string) $hook, 'simple-pos' ) ) || ( 0 === strpos( $page, 'simple-pos-' ) );
		if ( ! $is_pos_page ) {
			return;
		}

		$is_terminal = ( 'simple-pos-terminal' === $page ) || ( 'toplevel_page_simple-pos-terminal' === $hook );
		$is_barcode  = ( 'simple-pos-barcode' === $page );

		wp_enqueue_style( 'simple-pos-admin', SIMPLE_POS_PLUGIN_URL . 'admin/css/pos-admin.css', array(), SIMPLE_POS_VERSION );

		$shared_data = array(
			'restUrl'        => esc_url_raw( rest_url( Simple_POS_REST_API::NS ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'currency'       => array(
				'code'     => Simple_POS_Settings::get( 'currency_code', 'USD' ),
				'symbol'   => Simple_POS_Settings::get( 'currency_symbol', '$' ),
				'position' => Simple_POS_Settings::get( 'currency_position', 'before' ),
				'decimals' => (int) Simple_POS_Settings::get( 'decimal_places', 2 ),
			),
			'tax'            => array(
				'country' => Simple_POS_Settings::get( 'tax_country', 'US' ),
				'state'   => Simple_POS_Settings::get( 'tax_state', '' ),
			),
			'paperWidth'     => Simple_POS_Settings::get( 'paper_width', '80mm' ),
			'printerType'    => Simple_POS_Settings::get( 'printer_type', 'browser' ),
			'autoKickDrawer' => (int) Simple_POS_Settings::get( 'auto_kick_drawer', 0 ),
			'barcode'        => array(
				'symbology'   => Simple_POS_Settings::get( 'barcode_symbology', 'CODE39' ),
				'labelFormat' => Simple_POS_Settings::get( 'barcode_label_format', 'a4_30' ),
			),
			'storeName'      => Simple_POS_Settings::get( 'store_name', get_bloginfo( 'name' ) ),
			'storeAddress'   => Simple_POS_Settings::get( 'store_address', '' ),
			'storePhone'     => Simple_POS_Settings::get( 'store_phone', '' ),
			'storeEmail'     => Simple_POS_Settings::get( 'store_email', '' ),
			'storeGstin'     => Simple_POS_Settings::get( 'store_gstin', '' ),
			'receiptHeader'  => Simple_POS_Settings::get( 'receipt_header', '' ),
			'receiptFooter'  => Simple_POS_Settings::get( 'receipt_footer', '' ),
			'cashierName'    => wp_get_current_user()->display_name,
			'caps'           => array(
				'voidSales'       => current_user_can( 'void_pos_sales' ),
				'manageProducts'  => current_user_can( 'manage_pos_products' ),
				'manageCustomers' => current_user_can( 'manage_pos_customers' ),
			),
			// Offline sale queue (offline-mode add-on toggles this; on by default).
			'offlineQueue'   => ! class_exists( 'Simple_POS_Addons' ) || Simple_POS_Addons::is_enabled( 'simple-pos-offline-mode' ),
			'customerDisplay'=> class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-customer-display' ),
			'i18n'           => array(
				'confirmVoid'   => __( 'Void this sale and restore stock?', 'simple-pos' ),
				'confirmDelete' => __( 'Delete this item?', 'simple-pos' ),
				'cartEmpty'     => __( 'Cart is empty.', 'simple-pos' ),
				'checkoutError' => __( 'Checkout failed. Please try again.', 'simple-pos' ),
				'outOfStock'    => __( 'Out of stock', 'simple-pos' ),
				'paidIn'        => __( 'Paid in', 'simple-pos' ),
				'sync'          => __( 'Sync', 'simple-pos' ),
			),
		);

		// Vendor: JsBarcode (MIT) for barcode labels — enqueued locally, never CDN per WordPress.org guidelines.
		wp_register_script( 'simple-pos-jsbarcode', SIMPLE_POS_PLUGIN_URL . 'admin/js/vendor/jsbarcode.min.js', array(), '3.11.6', true );

		// Terminal screen: cart/checkout logic + barcode input.
		// NOTE: every POS submenu hook starts with "simple-pos-terminal_",
		// so match the exact Terminal page only — otherwise all admin
		// screens would get the terminal script and miss pos-admin.js.
		if ( $is_terminal ) {
			wp_enqueue_script( 'simple-pos-terminal', SIMPLE_POS_PLUGIN_URL . 'admin/js/pos-terminal.js', array(), SIMPLE_POS_VERSION, true );
			wp_localize_script( 'simple-pos-terminal', 'SimplePOS', $shared_data );
		}

		// Barcode labels screen needs JsBarcode + its screen script (both enqueued locally, never CDN).
		if ( $is_barcode ) {
			wp_enqueue_script( 'simple-pos-jsbarcode' );
			wp_enqueue_script( 'simple-pos-barcode-labels', SIMPLE_POS_PLUGIN_URL . 'admin/js/barcode-labels.js', array( 'simple-pos-jsbarcode', 'simple-pos-admin' ), SIMPLE_POS_VERSION, true );
		}

		// Shared admin script (products, customers, sales, reports, settings, taxes, barcode).
		// On the Terminal screen the terminal script owns the SimplePOS global; do not re-emit it here.
		if ( ! $is_terminal ) {
			wp_enqueue_script( 'simple-pos-admin', SIMPLE_POS_PLUGIN_URL . 'admin/js/pos-admin.js', array(), SIMPLE_POS_VERSION, true );
			wp_localize_script( 'simple-pos-admin', 'SimplePOS', $shared_data );
			// Expose vendor URL for print window (same origin, no CDN).
			wp_add_inline_script( 'simple-pos-admin', 'window.SimplePOSVendorUrl=' . wp_json_encode( SIMPLE_POS_PLUGIN_URL . 'admin/js/vendor/jsbarcode.min.js' ) . ';', 'before' );
		} else {
			// Terminal page still wants the vendor URL helper, but emit it on the terminal script.
			wp_add_inline_script( 'simple-pos-terminal', 'window.SimplePOSVendorUrl=' . wp_json_encode( SIMPLE_POS_PLUGIN_URL . 'admin/js/vendor/jsbarcode.min.js' ) . ';', 'before' );
		}
	}

	/**
	 * Front-end asset loader for the [simple_pos_terminal] shortcode.
	 * Enqueues the same CSS/JS as the admin terminal screen and hides the admin bar.
	 */
	public static function enqueue_frontend_assets() {
		static $done = false;
		if ( $done ) {
			return;
		}
		if ( ! self::$frontend_terminal_active ) {
			// Early <head> load: detect the shortcode before the_content runs.
			// (When rendering via shortcode we set the flag + call this again.)
			if ( function_exists( 'is_singular' ) && is_singular() ) {
				$post = get_queried_object();
				if ( $post instanceof WP_Post && function_exists( 'has_shortcode' ) && has_shortcode( (string) $post->post_content, 'simple_pos_terminal' ) ) {
					self::$frontend_terminal_active = true;
				}
			}
			if ( ! self::$frontend_terminal_active ) {
				return;
			}
		}
		wp_enqueue_style( 'simple-pos-admin', SIMPLE_POS_PLUGIN_URL . 'admin/css/pos-admin.css', array(), SIMPLE_POS_VERSION );

		$shared_data = array(
			'restUrl'        => esc_url_raw( rest_url( Simple_POS_REST_API::NS ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'currency'       => array(
				'code'     => Simple_POS_Settings::get( 'currency_code', 'USD' ),
				'symbol'   => Simple_POS_Settings::get( 'currency_symbol', '$' ),
				'position' => Simple_POS_Settings::get( 'currency_position', 'before' ),
				'decimals' => (int) Simple_POS_Settings::get( 'decimal_places', 2 ),
			),
			'tax'            => array(
				'country' => Simple_POS_Settings::get( 'tax_country', 'US' ),
				'state'   => Simple_POS_Settings::get( 'tax_state', '' ),
			),
			'paperWidth'     => Simple_POS_Settings::get( 'paper_width', '80mm' ),
			'printerType'    => Simple_POS_Settings::get( 'printer_type', 'browser' ),
			'autoKickDrawer' => (int) Simple_POS_Settings::get( 'auto_kick_drawer', 0 ),
			'barcode'        => array(
				'symbology'   => Simple_POS_Settings::get( 'barcode_symbology', 'CODE39' ),
				'labelFormat' => Simple_POS_Settings::get( 'barcode_label_format', 'a4_30' ),
			),
			'storeName'      => Simple_POS_Settings::get( 'store_name', get_bloginfo( 'name' ) ),
			'storeAddress'   => Simple_POS_Settings::get( 'store_address', '' ),
			'storePhone'     => Simple_POS_Settings::get( 'store_phone', '' ),
			'storeEmail'     => Simple_POS_Settings::get( 'store_email', '' ),
			'storeGstin'     => Simple_POS_Settings::get( 'store_gstin', '' ),
			'receiptHeader'  => Simple_POS_Settings::get( 'receipt_header', '' ),
			'receiptFooter'  => Simple_POS_Settings::get( 'receipt_footer', '' ),
			'cashierName'    => wp_get_current_user()->display_name,
			'caps'           => array(
				'voidSales'       => current_user_can( 'void_pos_sales' ),
				'manageProducts'  => current_user_can( 'manage_pos_products' ),
				'manageCustomers' => current_user_can( 'manage_pos_customers' ),
			),
			// Offline sale queue (offline-mode add-on toggles this; on by default).
			'offlineQueue'   => ! class_exists( 'Simple_POS_Addons' ) || Simple_POS_Addons::is_enabled( 'simple-pos-offline-mode' ),
			'customerDisplay'=> class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-customer-display' ),
			'i18n'           => array(
				'confirmVoid'   => __( 'Void this sale and restore stock?', 'simple-pos' ),
				'confirmDelete' => __( 'Delete this item?', 'simple-pos' ),
				'cartEmpty'     => __( 'Cart is empty.', 'simple-pos' ),
				'checkoutError' => __( 'Checkout failed. Please try again.', 'simple-pos' ),
				'outOfStock'    => __( 'Out of stock', 'simple-pos' ),
				'paidIn'        => __( 'Paid in', 'simple-pos' ),
				'sync'          => __( 'Sync', 'simple-pos' ),
			),
		);

		wp_enqueue_script( 'simple-pos-terminal', SIMPLE_POS_PLUGIN_URL . 'admin/js/pos-terminal.js', array(), SIMPLE_POS_VERSION, true );
		wp_localize_script( 'simple-pos-terminal', 'SimplePOS', $shared_data );
		wp_add_inline_script( 'simple-pos-terminal', 'window.SimplePOSVendorUrl=' . wp_json_encode( SIMPLE_POS_PLUGIN_URL . 'admin/js/vendor/jsbarcode.min.js' ) . ';', 'before' );
		$done = true;
	}

	/**
	 * [simple_pos_terminal] shortcode callback.
	 * Renders the terminal on the front-end and hides the admin bar.
	 */
	public static function render_shortcode_terminal() {
		if ( ! current_user_can( 'operate_pos' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to use the POS terminal.', 'simple-pos' ) . '</p>';
		}
		self::$frontend_terminal_active = true;
		show_admin_bar( false );
		// Shortcodes must RETURN markup (not echo). Buffer the view so it
		// lands in place, and enqueue assets now — the_content runs after
		// wp_enqueue_scripts, so the flag alone would be too late.
		self::enqueue_frontend_assets();
		ob_start();
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/terminal.php';
		return (string) ob_get_clean();
	}

	public static $frontend_terminal_active = false;

	/*
	---------------------------------------------------------------
	 * Page renderers — each delegates to a view file so this class
	 * stays focused on wiring, not markup.
	 * ------------------------------------------------------------- */

	public static function render_terminal_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/terminal.php';
	}
	public static function render_dashboard_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}
	public static function render_products_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/products.php';
	}
	public static function render_customers_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/customers.php';
	}
	public static function render_sales_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/sales.php';
	}
	public static function render_reports_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/reports.php';
	}
	public static function render_settings_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/settings.php';
	}
	public static function render_taxes_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/taxes.php';
	}
	public static function render_suppliers_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/suppliers.php';
	}
	public static function render_purchase_orders_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/purchase-orders.php';
	}
	public static function render_addons_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/addons.php';
	}

	/**
	 * Enable/disable an installed add-on (from the Add-ons screen toggle).
	 */
	public static function handle_addon_toggle() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage add-ons.', 'simple-pos' ) );
		}
		$addon = isset( $_POST['addon'] ) ? sanitize_key( wp_unslash( $_POST['addon'] ) ) : '';
		if ( '' === $addon ) {
			wp_safe_redirect( admin_url( 'admin.php?page=simple-pos-addons' ) );
			exit;
		}
		check_admin_referer( 'simple_pos_addon_toggle_' . $addon );

		$enable = ! empty( $_POST['enable'] ) && '1' === $_POST['enable'];
		Simple_POS_Addons::set_enabled( $addon, $enable );

		wp_safe_redirect( add_query_arg( 'simple_pos_addons_msg', 'updated', admin_url( 'admin.php?page=simple-pos-addons' ) ) );
		exit;
	}
	public static function render_barcode_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/barcode.php';
	}
	public static function render_backup_page() {
		include SIMPLE_POS_PLUGIN_DIR . 'admin/views/backup.php';
	}

	/**
	 * Handle the Settings form POST (admin-post.php).
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_settings' );

		Simple_POS_Settings::update( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		self::redirect_with_result( 'simple-pos-settings', true, __( 'Settings saved.', 'simple-pos' ) );
	}

	/**
	 * Save (create or update) a product from the classic admin form.
	 */
	public static function handle_save_product() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_product' );

		$data                = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id                  = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
		$data['track_stock'] = isset( $data['track_stock'] ) ? 1 : 0;

		$result = $id > 0
			? Simple_POS_Products::update_product( $id, $data )
			: Simple_POS_Products::create_product( $data );

		self::redirect_with_result( 'simple-pos-products', $result, __( 'Product saved.', 'simple-pos' ) );
	}

	/**
	 * Delete a product.
	 */
	public static function handle_delete_product() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_product' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid ID.', 'simple-pos' ) );
		}

		$result = Simple_POS_Products::delete_product( $id );
		self::redirect_with_result( 'simple-pos-products', $result, __( 'Product deleted.', 'simple-pos' ) );
	}

	/**
	 * Manually adjust (restock) a product's stock quantity.
	 */
	public static function handle_adjust_stock() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_adjust_stock' );

		$id    = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$delta = isset( $_POST['delta'] ) ? (int) $_POST['delta'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$note  = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = Simple_POS_Products::adjust_stock( $id, $delta, 'restock', null, $note );
		self::redirect_with_result( 'simple-pos-products', $result, __( 'Stock updated.', 'simple-pos' ) );
	}

	/**
	 * Save a category.
	 */
	public static function handle_save_category() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_category' );

		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = Simple_POS_Products::create_category( $name, $description );
		self::redirect_with_result( 'simple-pos-products', $result, __( 'Category added.', 'simple-pos' ) );
	}

	/**
	 * Delete a category.
	 */
	public static function handle_delete_category() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_category' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid ID.', 'simple-pos' ) );
		}

		Simple_POS_Products::delete_category( $id );
		self::redirect_with_result( 'simple-pos-products', true, __( 'Category deleted.', 'simple-pos' ) );
	}

	/**
	 * Save (create or update) a customer.
	 */
	public static function handle_save_customer() {
		if ( ! current_user_can( 'manage_pos_customers' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_customer' );

		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id   = isset( $data['customer_id'] ) ? (int) $data['customer_id'] : 0;

		$result = $id > 0
			? Simple_POS_Customers::update_customer( $id, $data )
			: Simple_POS_Customers::create_customer( $data );

		self::redirect_with_result( 'simple-pos-customers', $result, __( 'Customer saved.', 'simple-pos' ) );
	}

	/**
	 * Delete a customer.
	 */
	public static function handle_delete_customer() {
		if ( ! current_user_can( 'manage_pos_customers' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_customer' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid ID.', 'simple-pos' ) );
		}

		Simple_POS_Customers::delete_customer( $id );
		self::redirect_with_result( 'simple-pos-customers', true, __( 'Customer deleted.', 'simple-pos' ) );
	}

	public static function handle_save_tax_class() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_tax_class' );
		$name=isset($_POST['name'])? sanitize_text_field(wp_unslash($_POST['name'])):''; // phpcs:ignore
		$desc=isset($_POST['description'])? sanitize_textarea_field(wp_unslash($_POST['description'])):''; // phpcs:ignore
		$res  = Simple_POS_Tax::create_class( $name, $desc );
		self::redirect_with_result( 'simple-pos-taxes', $res, __( 'Tax class added.', 'simple-pos' ) );
	}
	public static function handle_delete_tax_class() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_tax_class' );
		Simple_POS_Tax::delete_class((int)$_GET['id']); // phpcs:ignore
		self::redirect_with_result( 'simple-pos-taxes', true, __( 'Tax class deleted.', 'simple-pos' ) );
	}
	public static function handle_save_tax_rate() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_tax_rate' );
		$data=wp_unslash($_POST); // phpcs:ignore
		$id      = isset( $data['rate_id'] ) ? (int) $data['rate_id'] : 0;
		$payload = array(
			'class_id'     => isset( $data['class_id'] ) ? (int) $data['class_id'] : 0,
			'country_code' => $data['country_code'] ?? '*',
			'state_code'   => $data['state_code'] ?? '',
			'rate'         => $data['rate'] ?? 0,
			'is_compound'  => ! empty( $data['is_compound'] ),
			'is_inclusive' => ! empty( $data['is_inclusive'] ),
			'priority'     => $data['priority'] ?? 0,
			'name'         => $data['name'] ?? '',
			'gst_split'    => ! empty( $data['gst_split'] ),
		);
		$res     = $id ? Simple_POS_Tax::update_rate( $id, $payload ) : Simple_POS_Tax::create_rate( $payload );
		self::redirect_with_result( 'simple-pos-taxes', $res, __( 'Tax rate saved.', 'simple-pos' ) );
	}
	public static function handle_delete_tax_rate() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_tax_rate' );
		Simple_POS_Tax::delete_rate((int)$_GET['id']); // phpcs:ignore
		self::redirect_with_result( 'simple-pos-taxes', true, __( 'Tax rate deleted.', 'simple-pos' ) );
	}
	public static function handle_save_variant() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_variant' );
		$data=wp_unslash($_POST); // phpcs:ignore
		$parent_id  = (int) ( $data['parent_product_id'] ?? 0 );
		$variant_id = (int) ( $data['variant_id'] ?? 0 );
		// attributes: attr_key[] / attr_value[]
		$attrs = array();
		if ( isset( $data['attr_key'] ) && is_array( $data['attr_key'] ) ) {
			foreach ( $data['attr_key'] as $i => $k ) {
				$v = $data['attr_value'][ $i ] ?? '';
				$k = sanitize_text_field( $k );
				$v = sanitize_text_field( $v );
				if ( $k !== '' && $v !== '' ) {
					$attrs[ $k ] = $v;
				}
			}
		}
		$payload = array(
			'sku'                 => $data['sku'] ?? '',
			'barcode'             => $data['barcode'] ?? '',
			'price'               => $data['price'] !== '' ? $data['price'] : null,
			'cost_price'          => $data['cost_price'] !== '' ? $data['cost_price'] : null,
			'stock_qty'           => $data['stock_qty'] ?? 0,
			'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
			'track_stock'         => isset( $data['track_stock'] ) ? 1 : 0,
			'image_url'           => $data['image_url'] ?? '',
			'hsn_sac_code'        => $data['hsn_sac_code'] ?? '',
			'attributes'          => $attrs,
			'status'              => $data['status'] ?? 'active',
		);
		$res     = $variant_id ? Simple_POS_Variants::update_variant( $variant_id, $payload ) : Simple_POS_Variants::create_variant( $parent_id, $payload );
		self::redirect_with_result( 'simple-pos-products', $res, __( 'Variant saved.', 'simple-pos' ) );
	}
	public static function handle_delete_variant() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_variant' );
		Simple_POS_Variants::delete_variant((int)$_GET['id']); // phpcs:ignore
		self::redirect_with_result( 'simple-pos-products', true, __( 'Variant deleted.', 'simple-pos' ) );
	}
	public static function handle_save_supplier() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_supplier' );
		$data=wp_unslash($_POST); // phpcs:ignore
		$id   = (int) ( $data['supplier_id'] ?? 0 );
		$res  = $id ? Simple_POS_Suppliers::update_supplier( $id, $data ) : Simple_POS_Suppliers::create_supplier( $data );
		self::redirect_with_result( 'simple-pos-suppliers', $res, __( 'Supplier saved.', 'simple-pos' ) );
	}
	public static function handle_delete_supplier() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_supplier' );
		Simple_POS_Suppliers::delete_supplier((int)$_GET['id']); // phpcs:ignore
		self::redirect_with_result( 'simple-pos-suppliers', true, __( 'Supplier deleted.', 'simple-pos' ) );
	}
	public static function handle_save_po() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_save_po' );
		$data=wp_unslash($_POST); // phpcs:ignore
		// items: product_id[], variant_id[], qty[], cost_price[]
		$items = array();
		if ( isset( $data['po_product_id'] ) && is_array( $data['po_product_id'] ) ) {
			foreach ( $data['po_product_id'] as $i => $pid ) {
				$vid  = $data['po_variant_id'][ $i ] ?? '';
				$qty  = $data['po_qty'][ $i ] ?? 1;
				$cost = $data['po_cost'][ $i ] ?? 0;
				if ( empty( $pid ) && empty( $vid ) ) {
					continue;
				}
				$items[] = array(
					'product_id' => (int) $pid,
					'variant_id' => $vid ? (int) $vid : null,
					'qty'        => (int) $qty,
					'cost_price' => (float) $cost,
				);
			}
		}
		$payload = array(
			'supplier_id' => $data['supplier_id'] ?? null,
			'note'        => $data['note'] ?? '',
			'items'       => $items,
		);
		$res     = Simple_POS_Purchase_Orders::create_order( $payload );
		self::redirect_with_result( 'simple-pos-purchase-orders', $res, __( 'Purchase order created.', 'simple-pos' ) );
	}
	public static function handle_receive_po() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_receive_po' );
		$po_id=(int)($_POST['po_id']??0); // phpcs:ignore
		$items = array();
		if(isset($_POST['receive_qty']) && is_array($_POST['receive_qty'])){ // phpcs:ignore
			foreach($_POST['receive_qty'] as $item_id=>$qty){ // phpcs:ignore
				$items[] = array(
					'item_id'      => (int) $item_id,
					'received_qty' => (int) $qty,
				);
			}
		}
		$res = Simple_POS_Purchase_Orders::receive( $po_id, $items );
		self::redirect_with_result( 'simple-pos-purchase-orders', $res, __( 'PO received, stock updated.', 'simple-pos' ) );
	}
	public static function handle_delete_po() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_delete_po' );
		Simple_POS_Purchase_Orders::delete_order((int)$_GET['id']); // phpcs:ignore
		self::redirect_with_result( 'simple-pos-purchase-orders', true, __( 'PO deleted.', 'simple-pos' ) );
	}
	public static function handle_import_products() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_import_products' );
		if(empty($_FILES['csv_file']['tmp_name'])){ // phpcs:ignore
			self::redirect_with_result( 'simple-pos-products', new WP_Error( 'pos_file_missing', __( 'No file uploaded.', 'simple-pos' ) ), '' );
			return;
		}
		$res=Simple_POS_CSV::import_products($_FILES['csv_file']['tmp_name']); // phpcs:ignore
		/* translators: %d: number of imported products. */
		$msg = is_wp_error( $res ) ? '' : sprintf( __( 'Imported %d products.', 'simple-pos' ), $res['imported'] );
		if ( ! empty( $res['errors'] ) ) {
			$msg .= ' ' . implode( ' ', array_slice( $res['errors'], 0, 3 ) );
		}
		self::redirect_with_result( 'simple-pos-products', is_wp_error( $res ) ? $res : true, $msg );
	}
	public static function handle_export_products() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_export_products' );
		$csv = Simple_POS_CSV::export_products();
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="pos-products-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
	public static function handle_export_sales() {
		if ( ! current_user_can( 'view_pos_sales' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_export_sales' );
		$from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Export with valid nonce (checked above); read-only date filter.
		$to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
		$csv  = Simple_POS_CSV::export_sales( $from, $to );
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="pos-sales-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Export all categories as CSV.
	 */
	public static function handle_export_categories() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_export_categories' );
		$csv = Simple_POS_CSV::export_categories();
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="pos-categories-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
	public static function handle_import_categories() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_import_categories' );
		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::redirect_with_result( 'simple-pos-products', new WP_Error( 'pos_file_missing', __( 'No file uploaded.', 'simple-pos' ) ), '' );
			return;
		}
		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$res = Simple_POS_CSV::import_categories( $tmp_name ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		/* translators: %d: number of imported categories. */
		$msg = is_wp_error( $res ) ? '' : sprintf( __( 'Imported %d categories.', 'simple-pos' ), $res['imported'] );
		if ( ! empty( $res['errors'] ) ) {
			$msg .= ' ' . implode( ' ', array_slice( $res['errors'], 0, 3 ) );
		}
		self::redirect_with_result( 'simple-pos-products', is_wp_error( $res ) ? $res : true, $msg );
	}

	/**
	 * Export all product variants as CSV.
	 */
	public static function handle_export_variants() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_export_variants' );
		$csv = Simple_POS_CSV::export_variants();
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="pos-variants-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Import product variants from CSV.
	 */
	public static function handle_import_variants() {
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_import_variants' );
		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::redirect_with_result( 'simple-pos-products', new WP_Error( 'pos_file_missing', __( 'No file uploaded.', 'simple-pos' ) ), '' );
			return;
		}
		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$res = Simple_POS_CSV::import_variants( $tmp_name ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		/* translators: %d: number of imported variants. */
		$msg = is_wp_error( $res ) ? '' : sprintf( __( 'Imported %d variants.', 'simple-pos' ), $res['imported'] );
		if ( ! empty( $res['errors'] ) ) {
			$msg .= ' ' . implode( ' ', array_slice( $res['errors'], 0, 3 ) );
		}
		self::redirect_with_result( 'simple-pos-products', is_wp_error( $res ) ? $res : true, $msg );
	}

	public static function handle_import_sales() {
		if ( ! current_user_can( 'view_pos_sales' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_import_sales' );
		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::redirect_with_result( 'simple-pos-sales', new WP_Error( 'pos_file_missing', __( 'No file uploaded.', 'simple-pos' ) ), '' );
			return;
		}
		$tmp_name = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$res = Simple_POS_CSV::import_sales( $tmp_name ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		/* translators: %d: number of imported sales. */
		$msg = is_wp_error( $res ) ? '' : sprintf( __( 'Imported %d sales.', 'simple-pos' ), $res['imported'] );
		if ( ! empty( $res['errors'] ) ) {
			$msg .= ' ' . implode( ' ', array_slice( $res['errors'], 0, 3 ) );
		}
		self::redirect_with_result( 'simple-pos-sales', is_wp_error( $res ) ? $res : true, $msg );
	}

	/**
	 * Export all plugin data as a JSON backup.
	 */
	public static function handle_backup_export() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_backup_export' );

		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		
		$backup = array(
			'_backup_version' => get_option( 'simple_pos_db_version', SIMPLE_POS_VERSION ),
			'_export_date'    => current_time( 'mysql' ),
			'_site_url'       => get_site_url(),
		);

		$tables = array(
			'settings'        => Simple_POS_Settings::get_all(),
			'products'        => $wpdb->get_results( "SELECT * FROM {$prefix}products" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'product_variants'=> $wpdb->get_results( "SELECT * FROM {$prefix}product_variants" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'customers'       => $wpdb->get_results( "SELECT * FROM {$prefix}customers" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'sales'           => $wpdb->get_results( "SELECT * FROM {$prefix}sales" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'sale_items'      => $wpdb->get_results( "SELECT * FROM {$prefix}sale_items" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'stock_log'       => $wpdb->get_results( "SELECT * FROM {$prefix}stock_log" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'suppliers'       => $wpdb->get_results( "SELECT * FROM {$prefix}suppliers" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'purchase_orders' => $wpdb->get_results( "SELECT * FROM {$prefix}purchase_orders" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'po_items'        => $wpdb->get_results( "SELECT * FROM {$prefix}po_items" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'tax_classes'     => $wpdb->get_results( "SELECT * FROM {$prefix}tax_classes" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'tax_rates'       => $wpdb->get_results( "SELECT * FROM {$prefix}tax_rates" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'categories'      => $wpdb->get_results( "SELECT * FROM {$prefix}categories" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$backup = array_merge( $backup, $tables );

		$json = wp_json_encode( $backup, JSON_PRETTY_PRINT );
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="simple-pos-backup-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_backup_import() {
		if ( ! current_user_can( 'manage_pos_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_backup_import' );

		if ( empty( $_FILES['backup_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['backup_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::redirect_with_result( 'simple-pos-backup', new WP_Error( 'pos_file_missing', __( 'No file uploaded.', 'simple-pos' ) ), '' );
			return;
		}

		$file_path = sanitize_text_field( wp_unslash( $_FILES['backup_file']['tmp_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$content   = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$data      = json_decode( $content, true );

		if ( ! is_array( $data ) ) {
			self::redirect_with_result( 'simple-pos-backup', new WP_Error( 'pos_invalid_backup', __( 'Invalid backup file.', 'simple-pos' ) ), '' );
			return;
		}

		// Validate backup version.
		if ( empty( $data['_backup_version'] ) ) {
			self::redirect_with_result( 'simple-pos-backup', new WP_Error( 'pos_invalid_backup', __( 'Backup file missing version information.', 'simple-pos' ) ), '' );
			return;
		}

		$backup_version   = $data['_backup_version'];
		$current_db_version = get_option( 'simple_pos_db_version', '0.0.0' );

		if ( version_compare( $backup_version, $current_db_version, '>' ) ) {
			self::redirect_with_result(
				'simple-pos-backup',
				new WP_Error(
					'pos_backup_version_mismatch',
					sprintf(
						/* translators: 1: backup version, 2: current version */
						__( 'Backup is from a newer version (%1$s) than current (%2$s). Please upgrade plugin first.', 'simple-pos' ),
						$backup_version,
						$current_db_version
					)
				),
				''
			);
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . SIMPLE_POS_TABLE_PREFIX;
		$allowed_tables = array(
			'settings',
			'products',
			'product_variants',
			'customers',
			'sales',
			'sale_items',
			'stock_log',
			'suppliers',
			'purchase_orders',
			'po_items',
			'tax_classes',
			'tax_rates',
			'categories',
		);

		// Use transaction for all-or-nothing import.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$success = true;

		foreach ( $allowed_tables as $table_name ) {
			if ( ! isset( $data[ $table_name ] ) || ! is_array( $data[ $table_name ] ) ) {
				continue;
			}
			$table = $prefix . $table_name;

			foreach ( $data[ $table_name ] as $row ) {
				if ( ! is_array( $row ) ) {
					$success = false;
					break 2;
				}

				// Validate row has expected columns.
				$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( array_keys( $row ) as $col ) {
				if ( ! in_array( $col, $columns, true ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'POS Backup: Invalid column \'' . $col . '\' in table ' . $table_name );
					}
					$success = false;
					break 3;
				}
			}

				if ( 'settings' === $table_name ) {
					Simple_POS_Settings::update( $row );
					continue;
				}

				$format  = array_fill( 0, count( $row ), '%s' );
				$result  = $wpdb->replace( $table, $row, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

				if ( false === $result ) {
					$success = false;
					break 2;
				}
			}
		}

		if ( $success ) {
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::redirect_with_result( 'simple-pos-backup', true, __( 'Backup restored successfully.', 'simple-pos' ) );
		} else {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::redirect_with_result( 'simple-pos-backup', new WP_Error( 'pos_backup_error', __( 'Backup restore failed. Data rolled back.', 'simple-pos' ) ), '' );
		}

		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Void a sale from the Sales History screen.
	 */
	public static function handle_void_sale() {
		if ( ! current_user_can( 'void_pos_sales' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-pos' ) );
		}
		check_admin_referer( 'simple_pos_void_sale' );

		$id   = isset( $_POST['sale_id'] ) ? (int) $_POST['sale_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$note = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = Simple_POS_Sales::void_sale( $id, $note );
		self::redirect_with_result( 'simple-pos-sales', $result, __( 'Sale voided and stock restored.', 'simple-pos' ) );
	}

	/**
	 * Redirect back to a plugin page with a one-time success/error notice
	 * stored in a per-user transient (avoids echoing raw messages back
	 * through the URL).
	 *
	 * @param string        $page          Plugin page slug.
	 * @param true|WP_Error $result       Result of the action.
	 * @param string        $success_text  Message to show on success.
	 */
	private static function redirect_with_result( $page, $result, $success_text ) {
		$message = is_wp_error( $result ) ? $result->get_error_message() : $success_text;
		$type    = is_wp_error( $result ) ? 'error' : 'success';

		set_transient(
			'simple_pos_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}

	/**
	 * Render the one-time notice set by redirect_with_result(), if any.
	 */
	public static function action_result_notice() {
		$key    = 'simple_pos_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );

		$css_class = 'error' === $notice['type'] ? 'notice-error' : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr( $css_class ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show a dismissible-per-load admin notice when products are low on
	 * stock. Only on plugin screens, and only for users who manage products.
	 */
	public static function low_stock_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'simple-pos' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_pos_products' ) ) {
			return;
		}

		$low_stock = Simple_POS_Products::get_low_stock_products( 6 );
		if ( empty( $low_stock ) ) {
			return;
		}

		$names = wp_list_pluck( $low_stock, 'name' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Simple POS:', 'simple-pos' ); ?></strong>
				<?php
				printf(
					/* translators: %s: comma-separated list of product names */
					esc_html__( 'Low stock alert for: %s', 'simple-pos' ),
					esc_html( implode( ', ', array_slice( $names, 0, 6 ) ) )
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products&filter=low_stock' ) ); ?>"><?php esc_html_e( 'View products', 'simple-pos' ); ?></a>
			</p>
		</div>
		<?php
	}
}
