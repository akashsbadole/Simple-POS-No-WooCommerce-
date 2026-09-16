<?php
/**
 * Plugin Name: Simple POS — Multi-Outlet
 * Description: Outlets with per-sale tagging and outlet-filtered sales history. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_POS_SMO_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'simple_pos_smo_boot' );

/**
 * Boot once the Simple POS core is available.
 */
function simple_pos_smo_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Multi-Outlet requires the free Simple POS plugin to be installed and active.', 'simple-pos' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-outlets.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'multi-outlet',
				'name'        => __( 'Multi-Outlet', 'simple-pos' ),
				'version'     => SIMPLE_POS_SMO_VERSION,
				'description' => __( 'Outlets with per-sale tagging and outlet-filtered sales history.', 'simple-pos' ),
			);
			return $addons;
		}
	);

	// Enabled/disabled is managed on the POS → Add-ons screen. When disabled
	// the add-on still announces itself (so it can be re-enabled) but boots
	// none of its features.
	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-multi-outlet' ) ) {
		return;
	}

	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Outlets', 'simple-pos' ),
				__( 'Outlets', 'simple-pos' ),
				'manage_pos_products',
				'simple-pos-outlets',
				'simple_pos_smo_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_outlet_save', 'simple_pos_smo_handle_save' );
	add_action( 'admin_post_simple_pos_outlet_delete', 'simple_pos_smo_handle_delete' );
	add_action( 'admin_post_simple_pos_outlet_default', 'simple_pos_smo_handle_default' );
}

/**
 * Render the Outlets screen.
 */
function simple_pos_smo_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage outlets.', 'simple-pos' ) );
	}
	Simple_POS_Smo_Outlets::ensure_schema();
	include __DIR__ . '/admin/views/outlets.php';
}

/**
 * Save (add/edit) an outlet.
 */
function simple_pos_smo_handle_save() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage outlets.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_outlet_save' );

	$id   = isset( $_POST['outlet_id'] ) ? (int) $_POST['outlet_id'] : 0;
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$addr = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';

	if ( '' === $name ) {
		wp_safe_redirect( add_query_arg( 'smo_msg', 'name_required', admin_url( 'admin.php?page=simple-pos-outlets' ) ) );
		exit;
	}

	if ( $id > 0 ) {
		Simple_POS_Smo_Outlets::update_outlet( $id, $name, $addr );
	} else {
		Simple_POS_Smo_Outlets::create_outlet( $name, $addr );
	}

	wp_safe_redirect( add_query_arg( 'smo_msg', 'saved', admin_url( 'admin.php?page=simple-pos-outlets' ) ) );
	exit;
}

/**
 * Delete an outlet (sales keep their history; outlet_id is nullable).
 */
function simple_pos_smo_handle_delete() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage outlets.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_outlet_delete' );

	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id > 0 ) {
		Simple_POS_Smo_Outlets::delete_outlet( $id );
	}

	wp_safe_redirect( add_query_arg( 'smo_msg', 'deleted', admin_url( 'admin.php?page=simple-pos-outlets' ) ) );
	exit;
}

/**
 * Set the default outlet preselected on the terminal.
 */
function simple_pos_smo_handle_default() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage outlets.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_outlet_default' );

	Simple_POS_Smo_Outlets::set_default_outlet_id( isset( $_POST['outlet_id'] ) ? (int) $_POST['outlet_id'] : 0 );

	wp_safe_redirect( add_query_arg( 'smo_msg', 'saved', admin_url( 'admin.php?page=simple-pos-outlets' ) ) );
	exit;
}
