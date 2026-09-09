<?php
/**
 * Plugin Name: Simple POS — Example Add-on
 * Description: Reference skeleton for paid Simple POS add-ons. Fork this folder into your own plugin to build one.
 * Version:     1.0.0
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * simple_pos_init fires once the Simple POS core is loaded (plugins_loaded:20),
 * so this works no matter which plugin loads first.
 */
add_action( 'simple_pos_init', function () {

	// Show up on the POS → Add-ons screen (toggleable there too).
	add_filter( 'simple_pos_registered_addons', function ( $addons ) {
		$addons[] = array(
			'slug'        => 'example',
			'name'        => __( 'Example Add-on', 'wp-pos-plugin' ),
			'version'     => '1.0.0',
			'description' => __( 'Logs every completed sale. Fork me into a paid add-on.', 'wp-pos-plugin' ),
		);
		return $addons;
	} );

	// React to every stored sale (id, totals/breakdown array, line items).
	add_action( 'simple_pos_sale_created', function ( $sale_id, $calc, $line_items ) {
		error_log( sprintf( '[simple-pos-example] sale #%d completed, total %s', $sale_id, $calc['total'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}, 10, 3 );

	// Other seams available:
	// add_filter( 'simple_pos_cart_data', fn( $cart ) => $cart );   // modify cart before totals
	// add_filter( 'simple_pos_order_calc', fn( $calc ) => $calc );  // modify computed totals
	// add_filter( 'simple_pos_addons_catalog', fn( $catalog ) => $catalog ); // change Add-ons screen
} );
