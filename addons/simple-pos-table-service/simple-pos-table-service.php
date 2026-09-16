<?php
/**
 * Plugin Name: Simple POS — Table Service
 * Description: Restaurant-style table management with active orders. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_POS_STS_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'simple_pos_sts_boot' );

function simple_pos_sts_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Table Service requires the free Simple POS plugin.', 'simple-pos' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-tables.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'table-service',
				'name'        => __( 'Table Service', 'simple-pos' ),
				'version'     => SIMPLE_POS_STS_VERSION,
				'description' => __( 'Restaurant-style table management with active orders.', 'simple-pos' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-table-service' ) ) {
		return;
	}

	Simple_POS_Sts_Tables::ensure_schema();

	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Tables', 'simple-pos' ),
				__( 'Tables', 'simple-pos' ),
				'manage_pos_products',
				'simple-pos-tables',
				'simple_pos_sts_render_page'
			);
		}
	);

	add_action( 'admin_post_simple_pos_table_save', 'simple_pos_sts_handle_save' );
	add_action( 'admin_post_simple_pos_table_delete', 'simple_pos_sts_handle_delete' );
	add_action( 'admin_post_simple_pos_table_status', 'simple_pos_sts_handle_status' );
}

function simple_pos_sts_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage tables.', 'simple-pos' ) );
	}
	include __DIR__ . '/admin/views/tables.php';
}

function simple_pos_sts_handle_save() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage tables.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_table_save' );

	$id    = isset( $_POST['table_id'] ) ? (int) $_POST['table_id'] : 0;
	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$seats = isset( $_POST['seats'] ) ? max( 1, (int) $_POST['seats'] ) : 1;

	if ( '' === $name ) {
		wp_safe_redirect( add_query_arg( 'sts_msg', 'name_required', admin_url( 'admin.php?page=simple-pos-tables' ) ) );
		exit;
	}

	if ( $id > 0 ) {
		Simple_POS_Sts_Tables::update_table( $id, $name, $seats );
	} else {
		Simple_POS_Sts_Tables::create_table( $name, $seats );
	}

	wp_safe_redirect( add_query_arg( 'sts_msg', 'saved', admin_url( 'admin.php?page=simple-pos-tables' ) ) );
	exit;
}

function simple_pos_sts_handle_delete() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage tables.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_table_delete' );

	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id > 0 ) {
		Simple_POS_Sts_Tables::delete_table( $id );
	}

	wp_safe_redirect( add_query_arg( 'sts_msg', 'deleted', admin_url( 'admin.php?page=simple-pos-tables' ) ) );
	exit;
}

function simple_pos_sts_handle_status() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage tables.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_table_status' );

	$id     = isset( $_POST['table_id'] ) ? (int) $_POST['table_id'] : 0;
	$status = isset( $_POST['table_status'] ) ? sanitize_text_field( wp_unslash( $_POST['table_status'] ) ) : 'available';

	if ( $id > 0 ) {
		Simple_POS_Sts_Tables::set_status( $id, $status );
	}

	wp_safe_redirect( add_query_arg( 'sts_msg', 'saved', admin_url( 'admin.php?page=simple-pos-tables' ) ) );
	exit;
}
