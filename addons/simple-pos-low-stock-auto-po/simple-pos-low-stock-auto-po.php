<?php
/**
 * Plugin Name: Simple POS — Low-Stock Auto-PO
 * Description: Automatically drafts purchase orders for low-stock products. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_POS_SAPO_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'simple_pos_sapo_boot' );
register_deactivation_hook( __FILE__, 'simple_pos_sapo_deactivate' );

/**
 * Boot once the Simple POS core is available.
 */
function simple_pos_sapo_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Simple POS — Low-Stock Auto-PO requires the free Simple POS plugin to be installed and active.', 'simple-pos' );
			echo '</p></div>';
		} );
		return;
	}

	require_once __DIR__ . '/includes/class-autopo.php';

	add_filter( 'simple_pos_registered_addons', function ( $addons ) {
		$addons[] = array(
			'slug'        => 'low-stock-auto-po',
			'name'        => __( 'Low-Stock Auto-PO', 'simple-pos' ),
			'version'     => SIMPLE_POS_SAPO_VERSION,
			'description' => __( 'Daily cron + one-click draft purchase orders for products below their low-stock threshold.', 'simple-pos' ),
		);
		return $addons;
	} );

	// Enabled/disabled is managed on the POS → Add-ons screen. When disabled
	// the add-on still announces itself (so it can be re-enabled) but boots
	// none of its features.
	if ( ! Simple_POS_Addons::is_enabled( 'low-stock-auto-po' ) ) {
		return;
	}

	add_action( 'admin_menu', function () {
		add_submenu_page(
			'simple-pos-terminal',
			__( 'Auto-PO', 'simple-pos' ),
			__( 'Auto-PO', 'simple-pos' ),
			'manage_pos_products',
			'simple-pos-autopo',
			'simple_pos_sapo_render_page'
		);
	} );

	add_action( 'admin_post_simple_pos_autopo_save', 'simple_pos_sapo_handle_save' );
	add_action( 'admin_post_simple_pos_autopo_generate', 'simple_pos_sapo_handle_generate' );
	add_action( 'simple_pos_autopo_cron', array( 'Simple_POS_Sapo_Auto_Po', 'run_daily' ) );

	if ( ! wp_next_scheduled( 'simple_pos_autopo_cron' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'simple_pos_autopo_cron' );
	}
}

/**
 * Clear the cron job on deactivation.
 */
function simple_pos_sapo_deactivate() {
	wp_clear_scheduled_hook( 'simple_pos_autopo_cron' );
}

/**
 * Render the Auto-PO screen.
 */
function simple_pos_sapo_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage purchase orders.', 'simple-pos' ) );
	}
	include __DIR__ . '/admin/views/auto-po.php';
}

/**
 * Save settings.
 */
function simple_pos_sapo_handle_save() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage purchase orders.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_autopo_save' );

	update_option(
		Simple_POS_Sapo_Auto_Po::OPTION_KEY,
		array(
			'enabled'      => isset( $_POST['enabled'] ) ? 1 : 0,
			'multiplier'   => isset( $_POST['multiplier'] ) ? max( 1, (float) $_POST['multiplier'] ) : 2,
			'min_qty'      => isset( $_POST['min_qty'] ) ? max( 1, (int) $_POST['min_qty'] ) : 1,
			'supplier_id'  => isset( $_POST['supplier_id'] ) ? (int) $_POST['supplier_id'] : 0,
			'notify_email' => isset( $_POST['notify_email'] ) ? sanitize_email( wp_unslash( $_POST['notify_email'] ) ) : '',
		)
	);

	wp_safe_redirect( add_query_arg( 'sapo_msg', 'saved', admin_url( 'admin.php?page=simple-pos-autopo' ) ) );
	exit;
}

/**
 * Generate a draft PO right now, then report back on the same screen.
 */
function simple_pos_sapo_handle_generate() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage purchase orders.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_autopo_generate' );

	$result = Simple_POS_Sapo_Auto_Po::generate();
	$args   = array( 'sapo_msg' => is_wp_error( $result ) ? 'error' : 'generated' );
	if ( ! is_wp_error( $result ) ) {
		$args['sapo_created'] = (int) $result['created'];
		$args['sapo_items']   = (int) $result['items'];
		$args['sapo_skipped'] = (int) $result['skipped'];
	}

	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=simple-pos-autopo' ) ) );
	exit;
}
