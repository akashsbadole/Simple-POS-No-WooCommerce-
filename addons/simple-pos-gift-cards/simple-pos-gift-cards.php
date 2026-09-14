<?php
/**
 * Plugin Name: Simple POS — Gift Cards
 * Description: Sell and redeem gift card codes. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SGC_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'sgc_boot' );

function sgc_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Gift Cards requires the free Simple POS plugin.', 'wp-pos-plugin' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-gift-cards.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'gift-cards',
				'name'        => __( 'Gift Cards', 'wp-pos-plugin' ),
				'version'     => SGC_VERSION,
				'description' => __( 'Sell, top up and redeem gift card codes at checkout.', 'wp-pos-plugin' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-gift-cards' ) ) {
		return;
	}

	SGC_Gift_Cards::ensure_schema();

	add_filter(
		'simple_pos_cart_data',
		function ( $cart_data ) {
			return SGC_Gift_Cards::apply_gift_card( $cart_data );
		}
	);

	// Consume the gift card balance once the sale is stored.
	add_action(
		'simple_pos_sale_created',
		function ( $sale_id, $calc, $line_items, $cart_data ) {
			$code = isset( $cart_data['gift_card_code'] ) ? $cart_data['gift_card_code'] : '';
			$used = isset( $cart_data['gift_card_applied'] ) ? (float) $cart_data['gift_card_applied'] : 0;
			if ( '' !== trim( (string) $code ) && $used > 0 ) {
				SGC_Gift_Cards::redeem( $code, $used );
			}
		},
		20,
		4
	);

	add_filter(
		'simple_pos_rest_product_args',
		function ( $args ) {
			return SGC_Gift_Cards::register_gift_card_product_arg( $args );
		}
	);

	add_action(
		'init',
		function () {
			register_rest_route(
				'simple-pos/v1',
				'/giftcards/lookup',
				array(
					'methods'             => 'GET',
					'callback'            => array( 'SGC_Gift_Cards', 'rest_lookup' ),
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
				__( 'Gift Cards', 'wp-pos-plugin' ),
				__( 'Gift Cards', 'wp-pos-plugin' ),
				'manage_pos_products',
				'simple-pos-gift-cards',
				'sgc_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_giftcard_save', 'sgc_handle_save' );
	add_action( 'admin_post_simple_pos_giftcard_topup', 'sgc_handle_topup' );
}

function sgc_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage gift cards.', 'wp-pos-plugin' ) );
	}
	include __DIR__ . '/admin/views/gift-cards.php';
}

function sgc_handle_save() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage gift cards.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_giftcard_save' );

	$code   = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
	$amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;

	if ( '' === $code || $amount <= 0 ) {
		wp_safe_redirect( add_query_arg( 'sgc_msg', 'invalid', admin_url( 'admin.php?page=simple-pos-gift-cards' ) ) );
		exit;
	}

	SGC_Gift_Cards::create_card( $code, $amount );

	wp_safe_redirect( add_query_arg( 'sgc_msg', 'created', admin_url( 'admin.php?page=simple-pos-gift-cards' ) ) );
	exit;
}

function sgc_handle_topup() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage gift cards.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_giftcard_topup' );

	$id     = isset( $_POST['card_id'] ) ? (int) $_POST['card_id'] : 0;
	$amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;

	if ( $id > 0 && $amount > 0 ) {
		SGC_Gift_Cards::top_up( $id, $amount );
	}

	wp_safe_redirect( add_query_arg( 'sgc_msg', 'topup', admin_url( 'admin.php?page=simple-pos-gift-cards' ) ) );
	exit;
}