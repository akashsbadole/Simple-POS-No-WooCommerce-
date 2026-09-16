<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
/**
 * Auto-PO screen: overview stats + low-stock preview + generation + settings.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$settings  = Simple_POS_Sapo_Auto_Po::get_settings();
$low       = Simple_POS_Sapo_Auto_Po::find_low_stock();
$suppliers = class_exists( 'Simple_POS_Suppliers' ) ? Simple_POS_Suppliers::get_suppliers() : array();

$msg     = isset( $_GET['sapo_msg'] ) ? sanitize_key( $_GET['sapo_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$created = isset( $_GET['sapo_created'] ) ? (int) $_GET['sapo_created'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$items_n = isset( $_GET['sapo_items'] ) ? (int) $_GET['sapo_items'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$skipped = isset( $_GET['sapo_skipped'] ) ? (int) $_GET['sapo_skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Totals for the overview cards.
$total_units = 0;
foreach ( $low as $p ) {
	$total_units += Simple_POS_Sapo_Auto_Po::suggest_qty( $p->stock_qty, $p->low_stock_threshold, $settings['multiplier'], $settings['min_qty'] );
}

$supplier_name = __( 'None', 'simple-pos' );
foreach ( (array) $suppliers as $s ) {
	if ( (int) $s->id === (int) $settings['supplier_id'] ) {
		$supplier_name = $s->name;
		break;
	}
}

$generate_url = wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_autopo_generate' ), 'simple_pos_autopo_generate' );
$has_low      = ! empty( $low );
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Low-Stock Auto-PO', 'simple-pos' ); ?></h1>
		<div class="simple-pos-page-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-purchase-orders' ) ); ?>"><?php esc_html_e( 'View purchase orders', 'simple-pos' ); ?></a>
			<?php if ( $has_low ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $generate_url ); ?>"><?php esc_html_e( 'Generate draft PO now', 'simple-pos' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'generated' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			if ( $created > 0 ) {
				printf(
					/* translators: 1: item count, 2: skipped count */
					esc_html__( 'Draft PO created with %1$d item(s); %2$d product(s) were already on an open PO and skipped.', 'simple-pos' ),
					(int) $items_n,
					(int) $skipped
				);
			} elseif ( $skipped > 0 ) {
				printf(
					/* translators: %d: skipped count */
					esc_html__( 'Nothing to order — %d product(s) are already on an open PO.', 'simple-pos' ),
					(int) $skipped
				);
			} else {
				esc_html_e( 'Nothing to order — no products are below their low-stock threshold.', 'simple-pos' );
			}
			?>
		</p></div>
	<?php elseif ( 'error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not generate the purchase order. Please try again.', 'simple-pos' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-stat-cards">
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Products to order', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( number_format_i18n( count( $low ) ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Units suggested', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( number_format_i18n( $total_units ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Automation', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value">
				<?php if ( ! empty( $settings['enabled'] ) ) : ?>
					<span class="simple-pos-status simple-pos-status-completed"><?php esc_html_e( 'Daily', 'simple-pos' ); ?></span>
				<?php else : ?>
					<span class="simple-pos-status simple-pos-status-inactive"><?php esc_html_e( 'Off', 'simple-pos' ); ?></span>
				<?php endif; ?>
			</span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Default supplier', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value-sm" title="<?php echo esc_attr( $supplier_name ); ?>"><?php echo esc_html( $supplier_name ); ?></span>
		</div>
	</div>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head">
					<h2 class="simple-pos-section-title"><?php esc_html_e( 'Low-stock products', 'simple-pos' ); ?></h2>
					<p><?php esc_html_e( 'Everything at or below its low-stock threshold. Generating a PO orders each line up to threshold × multiplier.', 'simple-pos' ); ?></p>
				</div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr><th><?php esc_html_e( 'Product', 'simple-pos' ); ?></th><th><?php esc_html_e( 'SKU', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Stock', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Threshold', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Suggested order', 'simple-pos' ); ?></th></tr></thead>
					<tbody>
						<?php if ( ! $has_low ) : ?>
							<tr><td colspan="5" class="simple-pos-empty">
								<?php esc_html_e( 'Nothing below the low-stock threshold. Nice.', 'simple-pos' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products' ) ); ?>"><?php esc_html_e( 'View products', 'simple-pos' ); ?></a>
							</td></tr>
						<?php endif; ?>
						<?php foreach ( $low as $p ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $p->name ); ?></strong><?php if ( ! empty( $p->variant_id ) ) : ?> <em class="simple-pos-pill"><?php esc_html_e( 'variant', 'simple-pos' ); ?></em><?php endif; ?></td>
								<td><code><?php echo esc_html( $p->sku ?: '—' ); ?></code></td>
								<td class="num"><span class="simple-pos-low-stock"><?php echo esc_html( $p->stock_qty ); ?></span></td>
								<td class="num simple-pos-muted"><?php echo esc_html( $p->low_stock_threshold ); ?></td>
								<td class="num"><strong><?php echo esc_html( number_format_i18n( Simple_POS_Sapo_Auto_Po::suggest_qty( $p->stock_qty, $p->low_stock_threshold, $settings['multiplier'], $settings['min_qty'] ) ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $has_low ) : ?>
					<div class="simple-pos-card-foot">
						<a class="button button-primary" href="<?php echo esc_url( $generate_url ); ?>"><?php esc_html_e( 'Generate draft PO now', 'simple-pos' ); ?></a>
						<span class="description">
							<?php
							if ( ! empty( $settings['last_run'] ) ) {
								printf(
									/* translators: %s: date/time */
									esc_html__( 'Last run: %s', 'simple-pos' ),
									esc_html( $settings['last_run'] )
								);
							} else {
								esc_html_e( 'Never run yet.', 'simple-pos' );
							}
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Settings', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_autopo_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_autopo_save" />
						<div class="simple-pos-form-row">
							<label class="simple-pos-checkbox"><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?> /> <?php esc_html_e( 'Generate automatically once a day (WP-Cron)', 'simple-pos' ); ?></label>
						</div>
						<div class="simple-pos-form-row">
							<label for="sapo-multiplier"><?php esc_html_e( 'Target multiplier', 'simple-pos' ); ?></label>
							<input id="sapo-multiplier" type="number" name="multiplier" min="1" step="0.5" value="<?php echo esc_attr( $settings['multiplier'] ); ?>" class="widefat" />
							<p class="description"><?php esc_html_e( 'Order enough to reach threshold × this value.', 'simple-pos' ); ?></p>
						</div>
						<div class="simple-pos-form-row">
							<label for="sapo-min-qty"><?php esc_html_e( 'Minimum order qty per product', 'simple-pos' ); ?></label>
							<input id="sapo-min-qty" type="number" name="min_qty" min="1" step="1" value="<?php echo esc_attr( $settings['min_qty'] ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sapo-supplier"><?php esc_html_e( 'Default supplier', 'simple-pos' ); ?></label>
							<select id="sapo-supplier" name="supplier_id" class="widefat">
								<option value="0"><?php esc_html_e( '— none —', 'simple-pos' ); ?></option>
								<?php foreach ( $suppliers as $s ) : ?>
									<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( (int) $settings['supplier_id'], (int) $s->id ); ?>><?php echo esc_html( $s->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="simple-pos-form-row">
							<label for="sapo-email"><?php esc_html_e( 'Notify email (optional)', 'simple-pos' ); ?></label>
							<input id="sapo-email" type="email" name="notify_email" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'you@store.com', 'simple-pos' ); ?>" />
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'simple-pos' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
