<?php
/**
 * Plugin Name: Simple POS — Online Ordering
 * Description: Public storefront (shortcode) that captures orders for the POS. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: wp-pos-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOO_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'soo_boot' );

function soo_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Online Ordering requires the free Simple POS plugin.', 'wp-pos-plugin' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-orders.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'online-ordering',
				'name'        => __( 'Online Ordering', 'wp-pos-plugin' ),
				'version'     => SOO_VERSION,
				'description' => __( 'Public storefront that captures orders for the terminal.', 'wp-pos-plugin' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-online-ordering' ) ) {
		return;
	}

	SOO_Orders::ensure_schema();

	add_shortcode( 'simple_pos_online_order', 'soo_render_storefront' );

	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'simple-pos-terminal',
				__( 'Online Orders', 'wp-pos-plugin' ),
				__( 'Online Orders', 'wp-pos-plugin' ),
				'manage_pos_products',
				'simple-pos-online-orders',
				'soo_render_page'
			);
		}
	);

	add_action(
		'init',
		function () {
			register_rest_route(
				'simple-pos/v1',
				'/online-orders',
				array(
					array(
						'methods'             => 'POST',
						'callback'            => array( 'SOO_Orders', 'rest_create' ),
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => 'GET',
						'callback'            => array( 'SOO_Orders', 'rest_list' ),
						'permission_callback' => function () {
							return current_user_can( 'simple_pos_use_terminal' );
						},
					),
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/online-orders/(?P<id>\d+)/status',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'SOO_Orders', 'rest_set_status' ),
					'permission_callback' => function () {
						return current_user_can( 'simple_pos_use_terminal' );
					},
				)
			);
		}
	);

	add_action( 'admin_post_simple_pos_online_order_status', 'soo_handle_status' );
}

/**
 * Storefront shortcode: [simple_pos_online_order]
 */
function soo_render_storefront() {
	wp_enqueue_script( 'simple-pos-online', plugins_url( 'assets/js/online-order.js', __FILE__ ), array(), SOO_VERSION, true );
	wp_localize_script(
		'simple-pos-online',
		'SimplePOSOnline',
		array(
			'restUrl'  => esc_url_raw( rest_url( 'simple-pos/v1' ) ),
			'currency' => array(
				'code'     => strtoupper( (string) Simple_POS_Settings::get( 'currency_code', 'USD' ) ),
				'symbol'   => Simple_POS_Settings::get( 'currency_symbol', '$' ),
				'decimals' => (int) Simple_POS_Settings::get( 'currency_decimals', 2 ),
				'position' => Simple_POS_Settings::get( 'currency_position', 'before' ),
			),
			'catalog'  => soo_online_catalog(),
			'i18n'     => array(
				'add'    => __( 'Add to order', 'wp-pos-plugin' ),
				'cart'   => __( 'Your order', 'wp-pos-plugin' ),
				'empty'  => __( 'Your order is empty.', 'wp-pos-plugin' ),
				'placed' => __( 'Order received — we will call you to confirm.', 'wp-pos-plugin' ),
				'failed' => __( 'Could not place the order. Please try again.', 'wp-pos-plugin' ),
			),
		)
	);

	ob_start();
	include __DIR__ . '/public/views/storefront.php';
	return ob_get_clean();
}

/**
 * Active products + categories for the public storefront. Only items that
 * can actually be sold online today are included.
 *
 * @return array
 */
function soo_online_catalog() {
	$products = Simple_POS_Products::get_products(
		array(
			'status' => 'active',
			'per_page' => 500,
		)
	);
	$items = array();
	foreach ( (array) ( isset( $products['items'] ) ? $products['items'] : array() ) as $p ) {
		if ( (int) $p->track_stock && (int) $p->stock_qty <= 0 ) {
			continue;
		}
		$items[] = array(
			'id'          => (int) $p->id,
			'name'        => $p->name,
			'price'       => (float) $p->price,
			'category_id' => (int) $p->category_id,
			'has_variants'=> ! empty( $p->has_variants ) || ! empty( $p->variant_count ),
			'out_of_stock'=> (int) $p->track_stock && (int) $p->stock_qty <= 0,
		);
	}
	$cats = array();
	foreach ( (array) Simple_POS_Products::get_categories() as $c ) {
		$cats[] = array(
			'id'   => (int) $c->id,
			'name' => $c->name,
		);
	}
	return array( 'products' => $items, 'categories' => $cats );
}

function soo_render_page() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to view online orders.', 'wp-pos-plugin' ) );
	}
	include __DIR__ . '/admin/views/orders.php';
}

function soo_handle_status() {
	if ( ! current_user_can( 'manage_pos_products' ) ) {
		wp_die( esc_html__( 'You are not allowed to update orders.', 'wp-pos-plugin' ) );
	}
	check_admin_referer( 'simple_pos_online_order_status' );

	$id     = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	$status = isset( $_POST['order_status'] ) ? sanitize_key( $_POST['order_status'] ) : 'pending';
	if ( $id > 0 ) {
		SOO_Orders::set_status( $id, $status );
	}

	wp_safe_redirect( add_query_arg( 'soo_msg', 'saved', admin_url( 'admin.php?page=simple-pos-online-orders' ) ) );
	exit;
}