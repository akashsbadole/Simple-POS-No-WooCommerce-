<?php
/**
 * Plugin Name: Simple POS — Offline Mode
 * Description: Toggle offline sale queueing with an admin dashboard. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOM_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'som_boot' );

function som_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Offline Mode requires the free Simple POS plugin.', 'wp-pos-plugin' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-offline.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'offline-mode',
				'name'        => __( 'Offline Mode', 'wp-pos-plugin' ),
				'version'     => SOM_VERSION,
				'description' => __( 'Toggle offline sale queueing with an admin dashboard.', 'wp-pos-plugin' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-offline-mode' ) ) {
		return;
	}

	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Offline Queue', 'wp-pos-plugin' ),
				__( 'Offline Queue', 'wp-pos-plugin' ),
				'manage_pos_products',
				'simple-pos-offline',
				'som_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_offline_toggle', 'som_handle_toggle' );
}

function som_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage offline settings.', 'wp-pos-plugin' ) );
	}
	include __DIR__ . '/admin/views/offline.php';
}

function som_handle_toggle() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage offline settings.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_offline_toggle' );
	SOM_Offline::toggle_enabled();
	wp_safe_redirect( add_query_arg( 'som_msg', 'saved', admin_url( 'admin.php?page=simple-pos-offline' ) ) );
	exit;
}