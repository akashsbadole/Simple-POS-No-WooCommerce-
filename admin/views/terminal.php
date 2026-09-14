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
	<h1 class="screen-reader-text"><?php esc_html_e( 'POS Terminal', 'wp-pos-plugin' ); ?></h1>

	<div id="simple-pos-terminal" class="simple-pos-terminal" data-loading="1">

		<div class="simple-pos-terminal-left">

			<div class="simple-pos-scan-row">
				<label for="simple-pos-scan-input" class="screen-reader-text"><?php esc_html_e( 'Scan barcode or SKU', 'wp-pos-plugin' ); ?></label>
				<input
					type="text"
					id="simple-pos-scan-input"
					class="simple-pos-scan-input"
					placeholder="<?php esc_attr_e( 'Scan barcode or type SKU, then press Enter…', 'wp-pos-plugin' ); ?>"
					autocomplete="off"
					autofocus
					aria-label="<?php esc_attr_e( 'Scan barcode or type SKU', 'wp-pos-plugin' ); ?>"
				/>
				<label for="simple-pos-search-input" class="screen-reader-text"><?php esc_html_e( 'Search products', 'wp-pos-plugin' ); ?></label>
				<input
					type="search"
					id="simple-pos-search-input"
					class="simple-pos-search-input"
					placeholder="<?php esc_attr_e( 'Search products…', 'wp-pos-plugin' ); ?>"
					autocomplete="off"
					aria-label="<?php esc_attr_e( 'Search products', 'wp-pos-plugin' ); ?>"
				/>
			</div>

			<div id="simple-pos-category-tabs" class="simple-pos-category-tabs" aria-label="<?php esc_attr_e( 'Filter by category', 'wp-pos-plugin' ); ?>"></div>

			<div id="simple-pos-product-grid" class="simple-pos-product-grid" aria-live="polite">
				<p class="simple-pos-loading-msg"><?php esc_html_e( 'Loading products…', 'wp-pos-plugin' ); ?></p>
			</div>

			<div class="simple-pos-pagination" id="simple-pos-product-pagination"></div>

		</div>

		<div class="simple-pos-terminal-right">

			<div class="simple-pos-cart-header">
				<h2><?php esc_html_e( 'Current Sale', 'wp-pos-plugin' ); ?></h2>
				<div class="simple-pos-cart-actions">
					<button type="button" id="simple-pos-hold-btn" class="button-link" disabled><?php esc_html_e( 'Hold', 'wp-pos-plugin' ); ?></button>
					<button type="button" id="simple-pos-recall-btn" class="button-link" disabled><?php esc_html_e( 'Recall', 'wp-pos-plugin' ); ?></button>
					<button type="button" id="simple-pos-sync-btn" class="button-link" hidden><?php esc_html_e( 'Sync', 'wp-pos-plugin' ); ?></button>
					<button type="button" id="simple-pos-clear-cart" class="button-link simple-pos-clear-link"><?php esc_html_e( 'Clear', 'wp-pos-plugin' ); ?></button>
				</div>
			</div>

			<div id="simple-pos-cart-items" class="simple-pos-cart-items" aria-live="polite" aria-relevant="additions removals">
				<p class="simple-pos-cart-empty"><?php esc_html_e( 'Cart is empty. Scan or click a product to add it.', 'wp-pos-plugin' ); ?></p>
			</div>

		<div class="simple-pos-customer-row">
			<label for="simple-pos-customer-select"><?php esc_html_e( 'Customer', 'wp-pos-plugin' ); ?></label>
			<select id="simple-pos-customer-select">
				<option value=""><?php esc_html_e( 'Walk-in customer', 'wp-pos-plugin' ); ?></option>
			</select>
			<button type="button" id="simple-pos-new-customer" class="button-link simple-pos-new-customer-btn" hidden aria-label="<?php esc_attr_e( 'Add new customer', 'wp-pos-plugin' ); ?>"><?php esc_html_e( '+ New', 'wp-pos-plugin' ); ?></button>
		</div>
		<div class="simple-pos-customer-row">
			<label for="simple-pos-customer-type"><?php esc_html_e( 'Type', 'wp-pos-plugin' ); ?></label>
			<select id="simple-pos-customer-type">
				<option value="b2c"><?php esc_html_e( 'B2C', 'wp-pos-plugin' ); ?></option>
				<option value="b2b"><?php esc_html_e( 'B2B', 'wp-pos-plugin' ); ?></option>
			</select>
		</div>
		<?php
		// Outlet selector (multi-outlet add-on). Hidden unless outlets exist.
		$pos_outlets = ( class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-multi-outlet' ) && class_exists( 'SMO_Outlets' ) ) ? SMO_Outlets::get_outlets() : array();
		if ( ! empty( $pos_outlets ) ) :
			$pos_default_outlet = class_exists( 'SMO_Outlets' ) ? (int) SMO_Outlets::get_default_outlet_id() : 0;
			?>
		<div class="simple-pos-customer-row">
			<label for="simple-pos-outlet"><?php esc_html_e( 'Outlet', 'wp-pos-plugin' ); ?></label>
			<select id="simple-pos-outlet">
				<?php foreach ( $pos_outlets as $pos_o ) : ?>
					<option value="<?php echo esc_attr( $pos_o->id ); ?>" <?php selected( $pos_default_outlet, (int) $pos_o->id ); ?>><?php echo esc_html( $pos_o->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>
		<?php
		// Table selector (table-service add-on). Hidden unless tables exist.
		$pos_tables = ( class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-table-service' ) && class_exists( 'STS_Tables' ) ) ? STS_Tables::get_tables() : array();
		if ( ! empty( $pos_tables ) ) :
			?>
		<div class="simple-pos-customer-row">
			<label for="simple-pos-table"><?php esc_html_e( 'Table', 'wp-pos-plugin' ); ?></label>
			<select id="simple-pos-table">
				<option value="0"><?php esc_html_e( '— none —', 'wp-pos-plugin' ); ?></option>
				<?php foreach ( $pos_tables as $pos_t ) : ?>
					<option value="<?php echo esc_attr( $pos_t->id ); ?>"><?php echo esc_html( $pos_t->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

			<div class="simple-pos-discount-row">
				<label for="simple-pos-discount-value"><?php esc_html_e( 'Discount', 'wp-pos-plugin' ); ?></label>
				<input type="number" min="0" step="0.01" id="simple-pos-discount-value" value="0" />
				<select id="simple-pos-discount-type">
					<option value="fixed"><?php esc_html_e( 'Amount', 'wp-pos-plugin' ); ?></option>
					<option value="percent">%</option>
				</select>
			</div>

		<div class="simple-pos-tax-row">
			<div class="simple-pos-tax-field">
				<label for="simple-pos-tax-country"><?php esc_html_e( 'Tax country', 'wp-pos-plugin' ); ?></label>
				<?php
				$pos_countries  = Simple_POS_Tax::get_configured_countries();
				$pos_current    = strtoupper( (string) Simple_POS_Settings::get( 'tax_country', 'US' ) );
				$pos_country_ns = Simple_POS_Tax::country_list();
				if ( $pos_countries && ! in_array( $pos_current, $pos_countries, true ) ) {
					$pos_countries[] = $pos_current;
					sort( $pos_countries );
				}
				if ( $pos_countries ) : ?>
					<select id="simple-pos-tax-country" aria-label="<?php esc_attr_e( 'Tax country', 'wp-pos-plugin' ); ?>">
						<?php foreach ( $pos_countries as $pos_cc ) : ?>
							<option value="<?php echo esc_attr( $pos_cc ); ?>" <?php selected( $pos_current, $pos_cc ); ?>><?php echo esc_html( isset( $pos_country_ns[ $pos_cc ] ) ? $pos_cc . ' — ' . $pos_country_ns[ $pos_cc ] : $pos_cc ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" id="simple-pos-tax-country" value="<?php echo esc_attr( $pos_current ); ?>" aria-label="<?php esc_attr_e( 'Tax country', 'wp-pos-plugin' ); ?>" />
				<?php endif; ?>
			</div>
			<div class="simple-pos-tax-field">
				<label for="simple-pos-tax-state"><?php esc_html_e( 'State', 'wp-pos-plugin' ); ?></label>
				<input type="text" id="simple-pos-tax-state" value="<?php echo esc_attr( Simple_POS_Settings::get( 'tax_state', '' ) ); ?>" aria-label="<?php esc_attr_e( 'State', 'wp-pos-plugin' ); ?>" />
			</div>
		</div>
			<div class="simple-pos-totals" id="simple-pos-totals">
				<div class="simple-pos-totals-row"><span><?php esc_html_e( 'Subtotal', 'wp-pos-plugin' ); ?></span><span id="simple-pos-subtotal">—</span></div>
				<div class="simple-pos-totals-row"><span><?php esc_html_e( 'Discount', 'wp-pos-plugin' ); ?></span><span id="simple-pos-discount-amount">—</span></div>
				<div class="simple-pos-totals-row"><span><?php esc_html_e( 'Tax', 'wp-pos-plugin' ); ?></span><span id="simple-pos-tax">—</span></div>
				<div id="simple-pos-tax-breakdown" style="font-size:11px;color:#646970"></div>
				<div class="simple-pos-totals-row simple-pos-totals-grand"><span><?php esc_html_e( 'Total', 'wp-pos-plugin' ); ?></span><span id="simple-pos-total">—</span></div>
			</div>

			<div class="simple-pos-payment-row">
				<label for="simple-pos-payment-method"><?php esc_html_e( 'Payment method', 'wp-pos-plugin' ); ?></label>
				<select id="simple-pos-payment-method">
					<option value="cash"><?php esc_html_e( 'Cash', 'wp-pos-plugin' ); ?></option>
					<option value="card"><?php esc_html_e( 'Card', 'wp-pos-plugin' ); ?></option>
					<?php if ( class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-gift-cards' ) && class_exists( 'SGC_Gift_Cards' ) ) : ?>
						<option value="gift_card"><?php esc_html_e( 'Gift Card', 'wp-pos-plugin' ); ?></option>
					<?php endif; ?>
					<option value="other"><?php esc_html_e( 'Other', 'wp-pos-plugin' ); ?></option>
				</select>
			</div>
			<?php if ( class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-gift-cards' ) && class_exists( 'SGC_Gift_Cards' ) ) : ?>
			<div class="simple-pos-payment-row">
				<label for="simple-pos-gift-code"><?php esc_html_e( 'Gift card code', 'wp-pos-plugin' ); ?></label>
				<input type="text" id="simple-pos-gift-code" autocomplete="off" placeholder="<?php esc_attr_e( 'Optional', 'wp-pos-plugin' ); ?>" />
			</div>
			<?php endif; ?>
			<?php if ( class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-loyalty' ) && class_exists( 'SLOY_Loyalty' ) ) : ?>
			<div class="simple-pos-payment-row">
				<label for="simple-pos-loyalty-points"><?php esc_html_e( 'Redeem loyalty points', 'wp-pos-plugin' ); ?></label>
				<input type="number" min="0" step="1" id="simple-pos-loyalty-points" placeholder="<?php esc_attr_e( '0', 'wp-pos-plugin' ); ?>" />
			</div>
			<?php endif; ?>
			<?php
			// Foreign-currency tender (multi-currency add-on). Hidden unless rates exist.
			$pos_fx_rates = ( class_exists( 'Simple_POS_Addons' ) && Simple_POS_Addons::is_enabled( 'simple-pos-multi-currency' ) && class_exists( 'SFX_Rates' ) ) ? SFX_Rates::get_rates() : array();
			$pos_base_ccy = strtoupper( (string) Simple_POS_Settings::get( 'currency_code', 'USD' ) );
			if ( ! empty( $pos_fx_rates ) ) :
				?>
			<div class="simple-pos-payment-row">
				<label for="simple-pos-currency"><?php esc_html_e( 'Currency', 'wp-pos-plugin' ); ?></label>
				<select id="simple-pos-currency" data-base="<?php echo esc_attr( $pos_base_ccy ); ?>">
					<option value="<?php echo esc_attr( $pos_base_ccy ); ?>" data-rate="1"><?php echo esc_html( $pos_base_ccy ); ?></option>
					<?php foreach ( $pos_fx_rates as $pos_fx ) : ?>
						<option value="<?php echo esc_attr( strtoupper( $pos_fx->currency_code ) ); ?>" data-rate="<?php echo esc_attr( $pos_fx->rate_to_base ); ?>"><?php echo esc_html( strtoupper( $pos_fx->currency_code ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="simple-pos-payment-row">
				<label for="simple-pos-amount-paid"><?php esc_html_e( 'Amount tendered', 'wp-pos-plugin' ); ?></label>
				<input type="number" min="0" step="0.01" id="simple-pos-amount-paid" />
			</div>

			<div class="simple-pos-totals-row simple-pos-change-row">
				<span><?php esc_html_e( 'Change due', 'wp-pos-plugin' ); ?></span><span id="simple-pos-change-due">—</span>
			</div>

			<button type="button" id="simple-pos-checkout-btn" class="button button-primary button-hero simple-pos-checkout-btn" disabled>
				<?php esc_html_e( 'Complete Sale', 'wp-pos-plugin' ); ?>
			</button>
			<button type="button" id="simple-pos-void-last-btn" class="button" style="display:none;width:100%;margin-top:6px"><?php esc_html_e( 'Void Last Sale', 'wp-pos-plugin' ); ?></button>

			<div id="simple-pos-cart-error" class="simple-pos-cart-error" role="alert" aria-live="assertive"></div>

		</div>
	</div>

	<!-- Variant picker modal -->
	<div id="simple-pos-variant-modal" class="simple-pos-modal-overlay" hidden>
		<div class="simple-pos-modal" role="dialog" aria-modal="true" aria-labelledby="simple-pos-variant-title"><h3 id="simple-pos-variant-title"><?php esc_html_e( 'Select variant', 'wp-pos-plugin' ); ?></h3><div id="simple-pos-variant-options"></div><button type="button" id="simple-pos-variant-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-pos-plugin' ); ?></button></div>
	</div>
	<!-- New customer modal -->
	<div id="simple-pos-new-customer-modal" class="simple-pos-modal-overlay" hidden>
		<form id="simple-pos-new-customer-form" class="simple-pos-modal simple-pos-new-customer-modal" role="dialog" aria-modal="true" aria-labelledby="simple-pos-new-customer-title" novalidate>
			<h3 id="simple-pos-new-customer-title"><?php esc_html_e( 'Add new customer', 'wp-pos-plugin' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Quick add. You can edit full details later from the Customers screen.', 'wp-pos-plugin' ); ?></p>
			<div class="simple-pos-form-row">
				<label for="simple-pos-new-customer-name"><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?> <span class="required" aria-hidden="true">*</span></label>
				<input type="text" id="simple-pos-new-customer-name" autocomplete="off" required />
			</div>
			<div class="simple-pos-form-row" style="margin-top:8px">
				<label for="simple-pos-new-customer-phone"><?php esc_html_e( 'Phone', 'wp-pos-plugin' ); ?></label>
				<input type="text" id="simple-pos-new-customer-phone" autocomplete="off" inputmode="tel" />
			</div>
			<div class="simple-pos-form-row" style="margin-top:8px">
				<label for="simple-pos-new-customer-email"><?php esc_html_e( 'Email', 'wp-pos-plugin' ); ?></label>
				<input type="email" id="simple-pos-new-customer-email" autocomplete="off" />
			</div>
			<div id="simple-pos-new-customer-error" class="simple-pos-cart-error" role="alert" aria-live="assertive" style="min-height:1.4em"></div>
			<div class="simple-pos-modal-actions">
				<button type="submit" id="simple-pos-new-customer-save" class="button button-primary"><?php esc_html_e( 'Add &amp; select', 'wp-pos-plugin' ); ?></button>
				<button type="button" id="simple-pos-new-customer-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-pos-plugin' ); ?></button>
			</div>
		</form>
	</div>
	<!-- Receipt modal, hidden until a sale completes -->
	<div id="simple-pos-receipt-modal" class="simple-pos-modal-overlay" hidden>
		<div class="simple-pos-modal" role="dialog" aria-modal="true" aria-labelledby="simple-pos-receipt-title">
			<h2 id="simple-pos-receipt-title" class="screen-reader-text"><?php esc_html_e( 'Receipt', 'wp-pos-plugin' ); ?></h2>
			<div id="simple-pos-receipt-content" class="simple-pos-receipt-print"></div>
			<div class="simple-pos-modal-actions">
				<button type="button" id="simple-pos-usb-print-receipt" class="button">USB Print</button>
				<button type="button" id="simple-pos-print-receipt" class="button button-primary"><?php esc_html_e( 'Print', 'wp-pos-plugin' ); ?></button>
				<button type="button" id="simple-pos-kick-drawer" class="button"><?php esc_html_e('Kick Drawer','wp-pos-plugin');?></button>
				<button type="button" id="simple-pos-void-receipt-btn" class="button" style="display:none"><?php esc_html_e('Void This Sale','wp-pos-plugin');?></button>
				<button type="button" id="simple-pos-close-receipt" class="button button-primary"><?php esc_html_e( 'New Sale', 'wp-pos-plugin' ); ?></button>
			</div>
		</div>
	</div>
</div>
