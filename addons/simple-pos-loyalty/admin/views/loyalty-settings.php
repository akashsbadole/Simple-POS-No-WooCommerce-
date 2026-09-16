<?php
/**
 * Loyalty settings screen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$settings = SLOY_Loyalty::get_settings();
$msg = isset( $_GET['loyalty_msg'] ) ? sanitize_key( $_GET['loyalty_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Loyalty Points', 'wp-pos-plugin' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card">
				<div class="simple-pos-card-head"><h2 class="simple-pos-section-title"><?php esc_html_e( 'How it works', 'wp-pos-plugin' ); ?></h2></div>
				<div class="inside" style="padding:16px">
					<ul style="list-style:disc inside;margin:0;line-height:2">
						<li><?php esc_html_e( 'Customers earn points on every sale based on the points-per-currency rate.', 'wp-pos-plugin' ); ?></li>
						<li><?php esc_html_e( 'Points are stored in a per-customer ledger. Voids reverse the earned points.', 'wp-pos-plugin' ); ?></li>
						<li><?php esc_html_e( 'At checkout, customers can redeem points for a fixed-amount discount.', 'wp-pos-plugin' ); ?></li>
						<li><?php esc_html_e( 'Enable the loyalty integration in POS → Add-ons to activate.', 'wp-pos-plugin' ); ?></li>
					</ul>
				</div>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Settings', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_loyalty_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_loyalty_save" />
						<div class="simple-pos-form-row">
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Enable loyalty points', 'wp-pos-plugin' ); ?>
							</label>
						</div>
						<div class="simple-pos-form-row">
							<label for="sloy-ppc"><?php esc_html_e( 'Points per currency unit', 'wp-pos-plugin' ); ?></label>
							<input id="sloy-ppc" type="number" name="points_per_currency" step="0.1" min="0" value="<?php echo esc_attr( $settings['points_per_currency'] ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sloy-cpp"><?php esc_html_e( 'Currency value per point', 'wp-pos-plugin' ); ?></label>
							<input id="sloy-cpp" type="number" name="currency_per_point" step="0.001" min="0" value="<?php echo esc_attr( $settings['currency_per_point'] ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sloy-min"><?php esc_html_e( 'Minimum points to redeem', 'wp-pos-plugin' ); ?></label>
							<input id="sloy-min" type="number" name="min_points_redeem" step="1" min="1" value="<?php echo esc_attr( $settings['min_points_redeem'] ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sloy-max"><?php esc_html_e( 'Max discount (% of subtotal)', 'wp-pos-plugin' ); ?></label>
							<input id="sloy-max" type="number" name="max_discount_pct" step="1" min="1" max="100" value="<?php echo esc_attr( $settings['max_discount_pct'] ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'wp-pos-plugin' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
