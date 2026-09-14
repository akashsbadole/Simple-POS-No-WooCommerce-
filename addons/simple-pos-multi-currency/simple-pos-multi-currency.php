<?php
/**
 * Plugin Name: Simple POS — Multi-Currency
 * Description: Accept foreign-currency tender with editable exchange rates. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFX_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'sfx_boot' );

function sfx_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Multi-Currency requires the free Simple POS plugin.', 'wp-pos-plugin' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-rates.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'multi-currency',
				'name'        => __( 'Multi-Currency', 'wp-pos-plugin' ),
				'version'     => SFX_VERSION,
				'description' => __( 'Accept foreign-currency tender with editable exchange rates.', 'wp-pos-plugin' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-multi-currency' ) ) {
		return;
	}

	SFX_Rates::ensure_schema();

	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Currencies', 'wp-pos-plugin' ),
				__( 'Currencies', 'wp-pos-plugin' ),
				'manage_pos_products',
				'simple-pos-currencies',
				'sfx_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_fx_save', 'sfx_handle_save' );
	add_action( 'admin_post_simple_pos_fx_delete', 'sfx_handle_delete' );
}

function sfx_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage currencies.', 'wp-pos-plugin' ) );
	}
	include __DIR__ . '/admin/views/rates.php';
}

function sfx_handle_save() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage currencies.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_fx_save' );

	$code = isset( $_POST['currency_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['currency_code'] ) ) ) : '';
	$rate = isset( $_POST['rate_to_base'] ) ? (float) $_POST['rate_to_base'] : 0;

	if ( 3 !== strlen( $code ) || $rate <= 0 ) {
		wp_safe_redirect( add_query_arg( 'sfx_msg', 'invalid', admin_url( 'admin.php?page=simple-pos-currencies' ) ) );
		exit;
	}

	SFX_Rates::upsert_rate( $code, $rate );

	wp_safe_redirect( add_query_arg( 'sfx_msg', 'saved', admin_url( 'admin.php?page=simple-pos-currencies' ) ) );
	exit;
}

function sfx_handle_delete() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage currencies.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_fx_delete' );

	$code = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ) : '';
	if ( 3 === strlen( $code ) ) {
		SFX_Rates::delete_rate( $code );
	}

	wp_safe_redirect( add_query_arg( 'sfx_msg', 'deleted', admin_url( 'admin.php?page=simple-pos-currencies' ) ) );
	exit;
}