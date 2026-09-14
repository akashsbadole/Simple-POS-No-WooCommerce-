<?php
/**
 * Public storefront markup. Products and categories are pulled from the
 * active POS catalog; interaction is handled by assets/js/online-order.js
 * which posts the order to the REST endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$store_cats = Simple_POS_Products::get_categories();
?>
<div id="simple-pos-online-store" class="simple-pos-online" data-rest="<?php echo esc_url_raw( rest_url( 'simple-pos/v1' ) ); ?>">
	<div class="simple-pos-online-grid">
		<div class="simple-pos-online-catalog">
			<div class="simple-pos-online-cats">
				<button type="button" class="is-active" data-cat="0"><?php esc_html_e( 'All', 'wp-pos-plugin' ); ?></button>
				<?php foreach ( $store_cats as $cat ) : ?>
					<button type="button" data-cat="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="simple-pos-online-products" id="simple-pos-online-products"></div>
		</div>
		<aside class="simple-pos-online-cart">
			<h2><?php esc_html_e( 'Your order', 'wp-pos-plugin' ); ?></h2>
			<div id="simple-pos-online-cart-items"></div>
			<div class="simple-pos-online-total">
				<span><?php esc_html_e( 'Total', 'wp-pos-plugin' ); ?></span>
				<strong id="simple-pos-online-total">0.00</strong>
			</div>
			<form id="simple-pos-online-checkout">
				<div class="simple-pos-form-row">
					<label for="soo-name"><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?> <span class="required">*</span></label>
					<input id="soo-name" type="text" required autocomplete="name" />
				</div>
				<div class="simple-pos-form-row">
					<label for="soo-phone"><?php esc_html_e( 'Phone', 'wp-pos-plugin' ); ?></label>
					<input id="soo-phone" type="tel" autocomplete="tel" />
				</div>
				<div class="simple-pos-form-row">
					<label for="soo-email"><?php esc_html_e( 'Email', 'wp-pos-plugin' ); ?></label>
					<input id="soo-email" type="email" autocomplete="email" />
				</div>
				<div class="simple-pos-form-row">
					<label for="soo-address"><?php esc_html_e( 'Delivery address', 'wp-pos-plugin' ); ?></label>
					<textarea id="soo-address" rows="2"></textarea>
				</div>
				<div id="simple-pos-online-message" role="alert" aria-live="assertive"></div>
				<button type="submit" class="button button-primary" style="width:100%"><?php esc_html_e( 'Place order', 'wp-pos-plugin' ); ?></button>
			</form>
		</aside>
	</div>
</div>