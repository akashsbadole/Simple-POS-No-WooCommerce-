<?php
/**
 * Uninstall handler.
 *
 * WordPress only executes this file when the plugin is deleted from the
 * Plugins screen (not on simple deactivation). Data is only removed if
 * the store owner explicitly checked "Delete all data on uninstall" in
 * Settings > Advanced — sales/product data should never vanish silently.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$simple_pos_settings = get_option( 'simple_pos_settings', array() );

if ( empty( $simple_pos_settings['delete_data_on_uninstall'] ) ) {
	return; // Leave everything in place.
}

global $wpdb;

// Multisite: loop over all sites when network-deleting.
if ( is_multisite() && function_exists( 'get_sites' ) && isset( $_GET['networkwide'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$simple_pos_sites = get_sites( array( 'number' => 0 ) );
	foreach ( $simple_pos_sites as $simple_pos_site ) {
		switch_to_blog( (int) $simple_pos_site->blog_id );
		simple_pos_uninstall_drop_tables();
		restore_current_blog();
	}
} else {
	simple_pos_uninstall_drop_tables();
}

function simple_pos_uninstall_drop_tables() {
	global $wpdb;
	$prefix = $wpdb->prefix . 'pos_';
	$tables = array(
		'po_items',
		'purchase_orders',
		'suppliers',
		'product_variants',
		'tax_rates',
		'tax_classes',
		'stock_log',
		'sale_items',
		'sales',
		'sale_sequences',
		'customers',
		'products',
		'categories',
	);
	// Disable FK checks so child tables drop cleanly regardless of order.
	$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}
	$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	delete_option( 'simple_pos_settings' );
	delete_option( 'simple_pos_db_version' );
	delete_option( 'simple_pos_vertical' );
	// Clean transients left by reports.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_simple_pos_%' OR option_name LIKE '_transient_timeout_simple_pos_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove custom roles/capabilities (single-site path already called inside function; for multisite loop, roles are per-site so this is extra safe).
if ( function_exists( 'remove_role' ) ) {
	remove_role( 'pos_cashier' );
	remove_role( 'pos_manager' );
	$simple_pos_admin = get_role( 'administrator' );
	if ( $simple_pos_admin ) {
		foreach ( array( 'operate_pos','view_pos_products','manage_pos_products','manage_pos_customers','view_pos_sales','void_pos_sales','view_pos_reports','manage_pos_settings' ) as $simple_pos_cap ) {
			$simple_pos_admin->remove_cap( $simple_pos_cap );
		}
	}
}
