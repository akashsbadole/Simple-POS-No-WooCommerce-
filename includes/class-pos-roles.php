<?php
/**
 * Custom roles and capabilities for the POS.
 *
 * Two dedicated roles keep POS access separate from general WP admin
 * access, so a store owner can hand a till to staff without giving them
 * the ability to edit posts, install plugins, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_POS_Roles {

	/**
	 * All capabilities the plugin defines.
	 *
	 * @return array
	 */
	public static function all_caps() {
		return array(
			'operate_pos',          // Use the POS terminal / checkout.
			'view_pos_products',    // View product list/search in terminal.
			'manage_pos_products',  // Create/edit/delete products & categories.
			'manage_pos_customers', // Create/edit/delete customers.
			'view_pos_sales',       // View sales history.
			'void_pos_sales',       // Void/refund a sale.
			'view_pos_reports',     // View reports/dashboard.
			'manage_pos_settings',  // Change plugin settings, manage staff roles.
		);
	}

	/**
	 * Register custom roles and grant capabilities to Administrator.
	 * Called on plugin activation.
	 */
	public static function add_roles_and_caps() {
		// Cashier: can operate the terminal and view products, nothing else.
		add_role(
			'pos_cashier',
			__( 'POS Cashier', 'simple-pos' ),
			array(
				'read'              => true,
				'operate_pos'       => true,
				'view_pos_products' => true,
				'view_pos_sales'    => true,
			)
		);

		// Manager: everything except core WP administration.
		add_role(
			'pos_manager',
			__( 'POS Manager', 'simple-pos' ),
			array(
				'read'                 => true,
				'operate_pos'          => true,
				'view_pos_products'    => true,
				'manage_pos_products'  => true,
				'manage_pos_customers' => true,
				'view_pos_sales'       => true,
				'void_pos_sales'       => true,
				'view_pos_reports'     => true,
			)
		);

		// Administrator gets every POS capability, including settings.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove custom roles (used on uninstall).
	 */
	public static function remove_roles_and_caps() {
		remove_role( 'pos_cashier' );
		remove_role( 'pos_manager' );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}
}
