<?php
/**
 * POS Terminal screen. Markup only — all behavior lives in pos-terminal.js,
 * which talks to the pos/v1 REST API registered in class-pos-rest-api.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap simple-pos-wrap simple-pos-terminal-wrap">
	<h1 class="screen-reader-text"><?php esc_html_e( 'POS Terminal', 'simple-pos' ); ?></h1>

	<div id="simple-pos-terminal" class="simple-pos-terminal" data-loading="1">

		<div class="simple-pos-terminal-left">

			<div class="simple-pos-scan-row">
				<input
					type="text"
					id="simple-pos-scan-input"
					class="simple-pos-scan-input"
					placeholder="<?php esc_attr_e( 'Scan barcode or type SKU, then press Enter…', 'simple-pos' ); ?>"
					autocomplete="off"
					autofocus
				/>
				<input
					type="search"
					id="simple-pos-search-input"
					class="simple-pos-search-input"
					placeholder="<?php esc_attr_e( 'Search products…', 'simple-pos' ); ?>"
					autocomplete="off"
				/>
			</div>

			<div id="simple-pos-category-tabs" class="simple-pos-category-tabs" aria-label="<?php esc_attr_e( 'Filter by category', 'simple-pos' ); ?>"></div>

			<div id="simple-pos-product-grid" class="simple-pos-product-grid" aria-live="polite">
				<p class="simple-pos-loading-msg"><?php esc_html_e( 'Loading products…', 'simple-pos' ); ?></p>
			</div>

			<div class="simple-pos-pagination" id="simple-pos-product-pagination"></div>

		</div>

		<div class="simple-pos-terminal-right">

			<div class="simple-pos-cart-header">
				<h2><?php esc_html_e( 'Current Sale', 'simple-pos' ); ?></h2>
				<button type="button" id="simple-pos-clear-cart" class="button-link simple-pos-clear-link"><?php esc_html_e( 'Clear', 'simple-pos' ); ?></button>
			</div>

			<div id="simple-pos-cart-items" class="simple-pos-cart-items">
				<p class="simple-pos-cart-empty"><?php esc_html_e( 'Cart is empty. Scan or click a product to add it.', 'simple-pos' ); ?></p>
			</div>

			<div class="simple-pos-customer-row">
				<label for="simple-pos-customer-select"><?php esc_html_e( 'Customer', 'simple-pos' ); ?></label>
				<select id="simple-pos-customer-select">
					<option value=""><?php esc_html_e( 'Walk-in customer', 'simple-pos' ); ?></option>
				</select>
			</div>

			<div class="simple-pos-discount-row">
				<label for="simple-pos-discount-value"><?php esc_html_e( 'Discount', 'simple-pos' ); ?></label>
				<input type="number" min="0" step="0.01" id="simple-pos-discount-value" value="0" />
				<select id="simple-pos-discount-type">
					<option value="fixed"><?php esc_html_e( 'Amount', 'simple-pos' ); ?></option>
					<option value="percent">%</option>
				</select>
			</div>

			<div style="display:flex;gap:8px;margin-bottom:8px">
				<label style="flex:1">Tax country <input type="text" id="simple-pos-tax-country" class="widefat" placeholder="US" style="width:80px" /></label>
				<label style="flex:1">State <input type="text" id="simple-pos-tax-state" class="widefat" placeholder="CA" style="width:80px" /></label>
			</div>
			<div class="simple-pos-totals" id="simple-pos-totals">
				<div class="simple-pos-totals-row"><span><?php esc_html_e( 'Subtotal', 'simple-pos' ); ?></span><span id="simple-pos-subtotal">—</span></div>
				<div class="simple-pos-totals-row"><span><?php esc_html_e( 'Discount', 'simple-pos' ); ?></span><span id="simple-pos-discount-amount">—</span></div>
				<div class="simple-pos-totals-row"><span><?php esc_html_e( 'Tax', 'simple-pos' ); ?></span><span id="simple-pos-tax">—</span></div>
				<div id="simple-pos-tax-breakdown" style="font-size:11px;color:#646970"></div>
				<div class="simple-pos-totals-row simple-pos-totals-grand"><span><?php esc_html_e( 'Total', 'simple-pos' ); ?></span><span id="simple-pos-total">—</span></div>
			</div>

			<div class="simple-pos-payment-row">
				<label for="simple-pos-payment-method"><?php esc_html_e( 'Payment method', 'simple-pos' ); ?></label>
				<select id="simple-pos-payment-method">
					<option value="cash"><?php esc_html_e( 'Cash', 'simple-pos' ); ?></option>
					<option value="card"><?php esc_html_e( 'Card', 'simple-pos' ); ?></option>
					<option value="other"><?php esc_html_e( 'Other', 'simple-pos' ); ?></option>
				</select>
			</div>

			<div class="simple-pos-payment-row">
				<label for="simple-pos-amount-paid"><?php esc_html_e( 'Amount tendered', 'simple-pos' ); ?></label>
				<input type="number" min="0" step="0.01" id="simple-pos-amount-paid" />
			</div>

			<div class="simple-pos-totals-row simple-pos-change-row">
				<span><?php esc_html_e( 'Change due', 'simple-pos' ); ?></span><span id="simple-pos-change-due">—</span>
			</div>

			<button type="button" id="simple-pos-checkout-btn" class="button button-primary button-hero simple-pos-checkout-btn" disabled>
				<?php esc_html_e( 'Complete Sale', 'simple-pos' ); ?>
			</button>

			<div id="simple-pos-cart-error" class="simple-pos-cart-error" role="alert"></div>

		</div>
	</div>

	<!-- Variant picker modal -->
	<div id="simple-pos-variant-modal" class="simple-pos-modal-overlay" hidden>
		<div class="simple-pos-modal" role="dialog" aria-modal="true"><h3>Select variant</h3><div id="simple-pos-variant-options"></div><button type="button" id="simple-pos-variant-cancel" class="button">Cancel</button></div>
	</div>
	<!-- Receipt modal, hidden until a sale completes -->
	<div id="simple-pos-receipt-modal" class="simple-pos-modal-overlay" hidden>
		<div class="simple-pos-modal" role="dialog" aria-modal="true" aria-labelledby="simple-pos-receipt-title">
			<div id="simple-pos-receipt-content" class="simple-pos-receipt-print"></div>
			<div class="simple-pos-modal-actions">
				<button type="button" id="simple-pos-usb-print-receipt" class="button">USB Print</button>
				<button type="button" id="simple-pos-print-receipt" class="button button-primary"><?php esc_html_e( 'Print', 'simple-pos' ); ?></button>
				<button type="button" id="simple-pos-kick-drawer" class="button"><?php esc_html_e('Kick Drawer','simple-pos');?></button>
				<button type="button" id="simple-pos-close-receipt" class="button"><?php esc_html_e( 'New Sale', 'simple-pos' ); ?></button>
			</div>
		</div>
	</div>
</div>
