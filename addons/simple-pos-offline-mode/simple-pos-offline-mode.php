<?php
/**
 * Plugin Name: Simple POS — Offline Mode
 * Description: Toggle offline sale queueing with an admin dashboard. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_POS_SOM_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'simple_pos_som_boot' );

function simple_pos_som_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Offline Mode requires the free Simple POS plugin.', 'simple-pos' );
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
				'name'        => __( 'Offline Mode', 'simple-pos' ),
				'version'     => SIMPLE_POS_SOM_VERSION,
				'description' => __( 'Toggle offline sale queueing with an admin dashboard.', 'simple-pos' ),
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
				__( 'Offline Queue', 'simple-pos' ),
				__( 'Offline Queue', 'simple-pos' ),
				'manage_pos_products',
				'simple-pos-offline',
				'simple_pos_som_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_offline_toggle', 'simple_pos_som_handle_toggle' );
}

function simple_pos_som_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage offline settings.', 'simple-pos' ) );
	}
	include __DIR__ . '/admin/views/offline.php';
}

function simple_pos_som_handle_toggle() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage offline settings.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_offline_toggle' );
	Simple_POS_Som_Offline::toggle_enabled();
	wp_safe_redirect( add_query_arg( 'som_msg', 'saved', admin_url( 'admin.php?page=simple-pos-offline' ) ) );
	exit;
}