<?php
/**
 * Plugin Name:       Simple POS (No WooCommerce)
 * Plugin URI:        https://example.com/simple-pos
 * Description:       A lightweight, standalone Point of Sale system for WordPress. No WooCommerce required. Custom database tables, REST API, barcode-ready terminal, per-country tax, variants, suppliers/POs, barcode labels, USB ESC/POS, inventory, reports and role-based access.
 * Version:           2.0.9
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        wp-pos-plugin
 * Domain Path:        /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin constants.
 */
define( 'SIMPLE_POS_VERSION', '2.0.9' );
define( 'SIMPLE_POS_DB_VERSION', '2.1.1' );
define( 'SIMPLE_POS_PLUGIN_FILE', __FILE__ );
define( 'SIMPLE_POS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_POS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_POS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMPLE_POS_TABLE_PREFIX', 'pos_' ); // appended to $wpdb->prefix, e.g. wp_pos_products

/**
 * Activation / deactivation hooks.
 * These classes must be loaded up front (outside the main init hook)
 * because register_activation_hook / register_deactivation_hook run
 * before plugins_loaded in some contexts (e.g. multisite network activation).
 */
require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-activator.php';
require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-deactivator.php';

register_activation_hook( __FILE__, array( 'Simple_POS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Simple_POS_Deactivator', 'deactivate' ) );

/**
 * Main plugin class. Loads dependencies and wires up hooks.
 * Kept intentionally dependency-free (no Composer autoload) so the
 * plugin works as a single drag-and-drop zip install.
 */
final class Simple_POS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Simple_POS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (or create) the singleton instance.
	 *
	 * @return Simple_POS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: load files and register hooks.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Require all class files.
	 */
	private function includes() {
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-db.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-roles.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-settings.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-tax.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-products.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-variants.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-customers.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-sales.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-suppliers.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-reports.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-rest-api.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-csv.php';
		require_once SIMPLE_POS_PLUGIN_DIR . 'includes/class-pos-addons.php';
		Simple_POS_Addons::load_enabled();

		if ( is_admin() ) {
			require_once SIMPLE_POS_PLUGIN_DIR . 'admin/class-pos-admin.php';
		}
	}

	/**
	 * Register top-level plugin hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ) );

		// Add-on boot: fires once every plugin file is loaded, so add-ons of
		// any load order can hook it (register filters, require files, etc).
		add_action( 'plugins_loaded', array( $this, 'fire_addon_init' ), 20 );

		// REST API.
		add_action( 'rest_api_init', array( 'Simple_POS_REST_API', 'register_routes' ) );

		// Admin UI.
		if ( is_admin() ) {
			Simple_POS_Admin::init();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-pos-plugin', false, dirname( SIMPLE_POS_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Fire the add-on bootstrap action (see Simple_POS_Addons docblock).
	 */
	public function fire_addon_init() {
		do_action( 'simple_pos_init' );
	}

	/**
	 * Run DB upgrades if the stored DB version is behind the plugin version.
	 * Keeps activation-time schema changes safe across plugin updates.
	 */
	public function maybe_upgrade_db() {
		$installed = get_option( 'simple_pos_db_version', '' );
		// version_compare (not !==) so installs stuck on 2.1.0 without the
		// sale_sequences table / FKs still upgrade when DB_VERSION bumps.
		if ( '' === $installed || version_compare( $installed, SIMPLE_POS_DB_VERSION, '<' ) ) {
			Simple_POS_Activator::create_tables();
			update_option( 'simple_pos_db_version', SIMPLE_POS_DB_VERSION );
		}
	}
}

/**
 * Boot the plugin once all plugins are available.
 */
function simple_pos() {
	return Simple_POS_Plugin::instance();
}
simple_pos();
require_once dirname( __FILE__ ) . '/includes/class-pos-verticals.php'; require_once dirname( __FILE__ ) . '/includes/class-pos-setup.php'; if ( is_admin() ) { Simple_POS_Setup::init(); } 
