<?php
/**
 * Plugin Name: Simple POS — Loyalty
 * Description: Customer loyalty points on purchases with redemption at checkout. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_POS_SLOY_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'simple_pos_sloy_boot' );

function simple_pos_sloy_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Loyalty requires the free Simple POS plugin.', 'simple-pos' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-loyalty.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'loyalty',
				'name'        => __( 'Loyalty Points', 'simple-pos' ),
				'version'     => SIMPLE_POS_SLOY_VERSION,
				'description' => __( 'Earn and redeem loyalty points on purchases.', 'simple-pos' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-loyalty' ) ) {
		return;
	}

	Simple_POS_Sloy_Loyalty::ensure_schema();

	// Award points on sale.
	add_action(
		'simple_pos_sale_created',
		function ( $sale_id, $calc, $line_items, $cart_data ) {
			Simple_POS_Sloy_Loyalty::award_points_for_sale( $sale_id, $calc, $line_items, $cart_data );
		},
		10,
		4
	);

	// Revoke points on void.
	add_action(
		'simple_pos_sale_voided',
		function ( $sale_id ) {
			Simple_POS_Sloy_Loyalty::revoke_points_for_sale( $sale_id );
		}
	);

	// Apply loyalty discount at checkout.
	add_filter(
		'simple_pos_cart_data',
		function ( $cart_data ) {
			return Simple_POS_Sloy_Loyalty::apply_cart_discount( $cart_data );
		}
	);

	// Register REST endpoints.
	add_action(
		'init',
		function () {
			register_rest_route(
				'simple-pos/v1',
				'/loyalty/balance',
				array(
					'methods'             => 'GET',
					'callback'            => array( 'Simple_POS_Sloy_Loyalty', 'rest_balance' ),
					'permission_callback' => function () {
						return current_user_can( 'operate_pos' );
					},
				)
			);
		}
	);

	// Admin page.
	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Loyalty', 'simple-pos' ),
				__( 'Loyalty', 'simple-pos' ),
				'manage_pos_products',
				'simple-pos-loyalty',
				'simple_pos_sloy_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_loyalty_save', 'simple_pos_sloy_handle_save' );
}

function simple_pos_sloy_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage loyalty settings.', 'simple-pos' ) );
	}
	include __DIR__ . '/admin/views/loyalty-settings.php';
}

function simple_pos_sloy_handle_save() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage loyalty settings.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_loyalty_save' );

	update_option(
		'simple_pos_loyalty',
		array(
			'points_per_currency' => isset( $_POST['points_per_currency'] ) ? (float) $_POST['points_per_currency'] : 1,
			'currency_per_point'  => isset( $_POST['currency_per_point'] ) ? (float) $_POST['currency_per_point'] : 0.01,
			'min_points_redeem'   => isset( $_POST['min_points_redeem'] ) ? (int) $_POST['min_points_redeem'] : 10,
			'max_discount_pct'    => isset( $_POST['max_discount_pct'] ) ? (float) $_POST['max_discount_pct'] : 50,
			'enabled'             => ! empty( $_POST['enabled'] ),
		)
	);

	wp_safe_redirect( add_query_arg( 'loyalty_msg', 'saved', admin_url( 'admin.php?page=simple-pos-loyalty' ) ) );
	exit;
}
