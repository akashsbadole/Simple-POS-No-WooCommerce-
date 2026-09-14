<?php
/**
 * Plugin Name: Simple POS — Time Clock
 * Description: Cashier clock-in/out with shift reports. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'STC_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'stc_boot' );

function stc_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Time Clock requires the free Simple POS plugin.', 'wp-pos-plugin' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-clock.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'time-clock',
				'name'        => __( 'Time Clock', 'wp-pos-plugin' ),
				'version'     => STC_VERSION,
				'description' => __( 'Cashier clock-in/out with shift reports.', 'wp-pos-plugin' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-time-clock' ) ) {
		return;
	}

	STC_Clock::ensure_schema();

	// REST API.
	add_action(
		'init',
		function () {
			register_rest_route(
				'simple-pos/v1',
				'/clock/in',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'STC_Clock', 'rest_clock_in' ),
					'permission_callback' => function () {
						return current_user_can( 'simple_pos_use_terminal' );
					},
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/clock/out',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'STC_Clock', 'rest_clock_out' ),
					'permission_callback' => function () {
						return current_user_can( 'simple_pos_use_terminal' );
					},
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/clock/status',
				array(
					'methods'             => 'GET',
					'callback'            => array( 'STC_Clock', 'rest_status' ),
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
				__( 'Shifts', 'wp-pos-plugin' ),
				__( 'Shifts', 'wp-pos-plugin' ),
				'manage_pos_products',
				'simple-pos-shifts',
				'stc_render_page'
			);
		}
	);
}

function stc_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to view shifts.', 'wp-pos-plugin' ) );
	}
	include __DIR__ . '/admin/views/shifts.php';
}