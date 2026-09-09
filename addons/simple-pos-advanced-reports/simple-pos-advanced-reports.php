<?php
/**
 * Plugin Name: Simple POS — Advanced Reports
 * Description: X/Z shift summaries, hourly heatmap, payment mix, product mix and tax summaries for Simple POS. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPAR_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'spar_boot' );

/**
 * Boot once the Simple POS core is available.
 */
function spar_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Simple POS — Advanced Reports requires the free Simple POS plugin to be installed and active.', 'simple-pos' );
			echo '</p></div>';
		} );
		return;
	}

	require_once __DIR__ . '/includes/class-spar-reports.php';

	add_filter( 'simple_pos_registered_addons', function ( $addons ) {
		$addons[] = array(
			'slug'        => 'advanced-reports',
			'name'        => __( 'Advanced Reports', 'simple-pos' ),
			'version'     => SPAR_VERSION,
			'description' => __( 'X/Z summaries, hourly heatmap, payment mix, product mix, tax summary and CSV exports.', 'simple-pos' ),
		);
		return $addons;
	} );

	// Enabled/disabled is managed on the POS → Add-ons screen. When disabled
	// the add-on still announces itself (so it can be re-enabled) but boots
	// none of its features.
	if ( ! Simple_POS_Addons::is_enabled( 'advanced-reports' ) ) {
		return;
	}

	add_action( 'admin_menu', function () {
		add_submenu_page(
			'simple-pos-terminal',
			__( 'Reports Pro', 'simple-pos' ),
			__( 'Reports Pro', 'simple-pos' ),
			'view_pos_reports',
			'simple-pos-adv-reports',
			'spar_render_page'
		);
	} );

	add_action( 'admin_post_simple_pos_adv_reports_export', 'spar_handle_export' );
}

/**
 * Render the Reports Pro screen.
 */
function spar_render_page() {
	if ( ! current_user_can( 'view_pos_reports' ) ) {
		wp_die( esc_html__( 'You are not allowed to view reports.', 'simple-pos' ) );
	}
	include __DIR__ . '/admin/views/reports-pro.php';
}

/**
 * CSV export handler: ?type=xz|hourly|payments|products|taxes plus date range.
 */
function spar_handle_export() {
	if ( ! current_user_can( 'view_pos_reports' ) ) {
		wp_die( esc_html__( 'You are not allowed to export reports.', 'simple-pos' ) );
	}
	check_admin_referer( 'simple_pos_adv_reports_export' );

	$type      = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
	$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-01' );
	$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );

	$data = SPAR_Reports::export( $type, $date_from, $date_to );
	if ( is_wp_error( $data ) || null === $data ) {
		wp_die( esc_html__( 'Unknown report type.', 'simple-pos' ) );
	}

	$filename = 'simple-pos-' . $type . '-' . $date_from . '-to-' . $date_to . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	echo $data['csv']; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV body, quoting handled by to_csv().
	exit;
}
