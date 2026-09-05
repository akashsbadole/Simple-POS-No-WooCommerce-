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

$settings = get_option( 'simple_pos_settings', array() );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return; // Leave everything in place.
}

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
	'customers',
	'products',
	'categories',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'simple_pos_settings' );
delete_option( 'simple_pos_db_version' );

// Remove custom roles/capabilities.
if ( function_exists( 'remove_role' ) ) {
	remove_role( 'pos_cashier' );
	remove_role( 'pos_manager' );
}
