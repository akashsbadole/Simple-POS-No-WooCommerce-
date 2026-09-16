<?php
/**
 * Plugin Name: Simple POS — Customer Display
 * Description: Secondary-screen customer view. Terminal pushes cart updates; a public shortcode polls and renders them. Requires the free Simple POS plugin.
 * Version:     1.0.0
 * Author:      Simple POS
 * Text Domain: simple-pos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_POS_SCD_VERSION', '1.0.0' );

add_action( 'simple_pos_init', 'simple_pos_scd_boot' );

function simple_pos_scd_boot() {
	if ( ! class_exists( 'Simple_POS_Addons' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Simple POS — Customer Display requires the free Simple POS plugin.', 'simple-pos' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once __DIR__ . '/includes/class-display.php';

	add_filter(
		'simple_pos_registered_addons',
		function ( $addons ) {
			$addons[] = array(
				'slug'        => 'customer-display',
				'name'        => __( 'Customer Display', 'simple-pos' ),
				'version'     => SIMPLE_POS_SCD_VERSION,
				'description' => __( 'Secondary-screen customer view for the POS terminal.', 'simple-pos' ),
			);
			return $addons;
		}
	);

	if ( ! Simple_POS_Addons::is_enabled( 'simple-pos-customer-display' ) ) {
		return;
	}

	add_action(
		'init',
		function () {
			register_rest_route(
				'simple-pos/v1',
				'/display/update',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'Simple_POS_Scd_Display', 'rest_update' ),
					'permission_callback' => function () {
						return current_user_can( 'operate_pos' );
					},
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/display/current',
				array(
					'methods'             => 'GET',
					'callback'            => array( 'Simple_POS_Scd_Display', 'rest_current' ),
					'permission_callback' => '__return_true',
				)
			);
			register_rest_route(
				'simple-pos/v1',
				'/display/clear',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'Simple_POS_Scd_Display', 'rest_clear' ),
					'permission_callback' => function () {
						return current_user_can( 'operate_pos' );
					},
				)
			);
		}
	);

	add_shortcode( 'simple_pos_customer_display', 'simple_pos_scd_render_shortcode' );
}

/**
 * Render the customer display page.
 * [simple_pos_customer_display refresh="1000"]
 */
function simple_pos_scd_render_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'refresh' => 1000,
		),
		$atts
	);

	wp_enqueue_script( 'simple-pos-customer-display', plugins_url( 'assets/js/customer-display.js', __FILE__ ), array(), SIMPLE_POS_SCD_VERSION, true );
	wp_localize_script(
		'simple-pos-customer-display',
		'SimplePOSDisplay',
		array(
			'restUrl'  => esc_url_raw( rest_url( 'simple-pos/v1' ) ),
			'refresh'  => max( 500, (int) $atts['refresh'] ),
			'currency' => array(
				'code'     => strtoupper( (string) Simple_POS_Settings::get( 'currency_code', 'USD' ) ),
				'symbol'   => Simple_POS_Settings::get( 'currency_symbol', '$' ),
				'decimals' => (int) Simple_POS_Settings::get( 'currency_decimals', 2 ),
				'position' => Simple_POS_Settings::get( 'currency_position', 'before' ),
			),
			'i18n'     => array(
				'empty'      => __( 'Ready for your order', 'simple-pos' ),
				'subtotal'   => __( 'Subtotal', 'simple-pos' ),
				'discount'   => __( 'Discount', 'simple-pos' ),
				'tax'        => __( 'Tax', 'simple-pos' ),
				'total'      => __( 'Total', 'simple-pos' ),
			),
		)
	);

	ob_start();
	?>
	<div id="simple-pos-customer-display" class="simple-pos-customer-display" aria-live="polite">
		<div class="scd-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<h1 class="scd-store-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<?php endif; ?>
		</div>
		<div id="scd-items" class="scd-items"></div>
		<div id="scd-totals" class="scd-totals"></div>
	</div>
	<?php
	return ob_get_clean();
}