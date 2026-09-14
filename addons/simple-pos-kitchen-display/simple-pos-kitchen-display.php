<?php
/**
 * Plugin Name: Simple POS — Kitchen Display
 * Description: Kitchen order view with item-level prep status for restaurant POS. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKD_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'skd_boot' );

function skd_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Kitchen Display requires the free Simple POS plugin.', 'wp-pos-plugin' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-kitchen.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'kitchen-display',
				'name'        => __( 'Kitchen Display', 'wp-pos-plugin' ),
				'version'     => SKD_VERSION,
				'description' => __( 'Restaurant kitchen order view with item-level prep status.', 'wp-pos-plugin' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-kitchen-display' ) ) {
		return;
	}

	SKD_Kitchen::ensure_schema();

	// Tag new sale items for kitchen when a sale is created.
	add_action(
		'simple_pos_sale_created',
		function ( $sale_id, $calc, $line_items, $cart_data ) {
			SKD_Kitchen::queue_sale_items( $sale_id, $line_items );
		},
		10,
		4
	);

	// REST API for kitchen operations.
	add_action(
		'init',
		function () {
			register_rest_route(
				'simple-pos/v1',
				'/kitchen/orders',
				array(
					'methods'             => 'GET',
					'callback'            => array( 'SKD_Kitchen', 'rest_list' ),
					'permission_callback' => function () {
						return current_user_can( 'simple_pos_use_terminal' );
					},
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/kitchen/items/(?P<id>\d+)/status',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'SKD_Kitchen', 'rest_set_item_status' ),
					'permission_callback' => function () {
						return current_user_can( 'simple_pos_use_terminal' );
					},
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/kitchen/orders/(?P<id>\d+)/bump',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'SKD_Kitchen', 'rest_bump_order' ),
					'permission_callback' => function () {
						return current_user_can( 'simple_pos_use_terminal' );
					},
				)
			);
		}
	);

	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Kitchen Display', 'wp-pos-plugin' ),
				__( 'Kitchen Display', 'wp-pos-plugin' ),
				'manage_pos_products',
				'simple-pos-kitchen',
				'skd_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_kitchen_bump', 'skd_handle_bump' );
}

function skd_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to access the kitchen display.', 'wp-pos-plugin' ) );
	}
	include __DIR__ . '/admin/views/kitchen.php';
}

function skd_handle_bump() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to bump orders.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_kitchen_bump' );

	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	if ( $order_id > 0 ) {
		SKD_Kitchen::bump_order( $order_id );
	}

	wp_safe_redirect( add_query_arg( 'skd_msg', 'bumped', admin_url( 'admin.php?page=simple-pos-kitchen' ) ) );
	exit;
}