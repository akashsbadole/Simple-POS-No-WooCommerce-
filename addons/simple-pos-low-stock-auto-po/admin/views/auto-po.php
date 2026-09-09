<?php
/**
 * Auto-PO screen: settings + low-stock preview + manual generation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$settings  = SAPO_AutoPO::get_settings();
$low       = SAPO_AutoPO::find_low_stock();
$suppliers = class_exists( 'Simple_POS_Suppliers' ) ? Simple_POS_Suppliers::get_suppliers() : array();

$msg     = isset( $_GET['sapo_msg'] ) ? sanitize_key( $_GET['sapo_msg'] ) : '';
$created = isset( $_GET['sapo_created'] ) ? (int) $_GET['sapo_created'] : 0;
$items_n = isset( $_GET['sapo_items'] ) ? (int) $_GET['sapo_items'] : 0;
$skipped = isset( $_GET['sapo_skipped'] ) ? (int) $_GET['sapo_skipped'] : 0;

$generate_url = wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_autopo_generate' ), 'simple_pos_autopo_generate' );
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Low-Stock Auto-PO', 'wp-pos-plugin' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'wp-pos-plugin' ); ?></p></div>
	<?php elseif ( 'generated' === $msg ) : ?>
		<div class="notice notice-success"><p>
			<?php
			if ( $created > 0 ) {
				printf(
					/* translators: 1: item count, 2: skipped count */
					esc_html__( 'Draft PO created with %1$d item(s); %2$d product(s) were already on an open PO and skipped.', 'wp-pos-plugin' ),
					(int) $items_n,
					(int) $skipped
				);
			} elseif ( $skipped > 0 ) {
				printf(
					/* translators: %d: skipped count */
					esc_html__( 'Nothing to order — %d product(s) are already on an open PO.', 'wp-pos-plugin' ),
					(int) $skipped
				);
			} else {
				esc_html_e( 'Nothing to order — no products are below their low-stock threshold.', 'wp-pos-plugin' );
			}
			?>
		</p></div>
	<?php elseif ( 'error' === $msg ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Could not generate the purchase order. Please try again.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e( 'Low-stock products', 'wp-pos-plugin' ); ?></h2>
				<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
					<thead><tr><th><?php esc_html_e( 'Product', 'wp-pos-plugin' ); ?></th><th><?php esc_html_e( 'SKU', 'wp-pos-plugin' ); ?></th><th class="num"><?php esc_html_e( 'Stock', 'wp-pos-plugin' ); ?></th><th class="num"><?php esc_html_e( 'Threshold', 'wp-pos-plugin' ); ?></th><th class="num"><?php esc_html_e( 'Suggested order', 'wp-pos-plugin' ); ?></th></tr></thead>
					<tbody>
						<?php if ( empty( $low ) ) : ?><tr><td colspan="5" class="simple-pos-empty"><?php esc_html_e( 'Nothing below the low-stock threshold. Nice.', 'wp-pos-plugin' ); ?></td></tr><?php endif; ?>
						<?php foreach ( $low as $p ) : ?>
							<tr>
								<td><?php echo esc_html( $p->name ); ?></td>
								<td><code><?php echo esc_html( $p->sku ?: '—' ); ?></code></td>
								<td class="num"><?php echo esc_html( $p->stock_qty ); ?></td>
								<td class="num"><?php echo esc_html( $p->low_stock_threshold ); ?></td>
								<td class="num"><strong><?php echo esc_html( SAPO_AutoPO::suggest_qty( $p->stock_qty, $p->low_stock_threshold, $settings['multiplier'], $settings['min_qty'] ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p style="padding:0 14px 14px">
					<a class="button button-primary" href="<?php echo esc_url( $generate_url ); ?>"><?php esc_html_e( 'Generate draft PO now', 'wp-pos-plugin' ); ?></a>
					<?php if ( $settings['last_run'] ) : ?>
						<span class="description" style="margin-left:10px">
							<?php
							printf(
								/* translators: %s: date/time */
								esc_html__( 'Last run: %s', 'wp-pos-plugin' ),
								esc_html( $settings['last_run'] )
							);
							?>
						</span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="simple-pos-form-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-card simple-pos-filters">
				<?php wp_nonce_field( 'simple_pos_autopo_save' ); ?>
				<input type="hidden" name="action" value="simple_pos_autopo_save" />
				<h2 class="simple-pos-section-title" style="margin:0 0 10px"><?php esc_html_e( 'Settings', 'wp-pos-plugin' ); ?></h2>
				<div class="simple-pos-form-row">
					<label class="simple-pos-checkbox"><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?> /> <?php esc_html_e( 'Generate automatically once a day (WP-Cron)', 'wp-pos-plugin' ); ?></label>
				</div>
				<div class="simple-pos-form-row" style="margin-top:8px">
					<label><?php esc_html_e( 'Target multiplier', 'wp-pos-plugin' ); ?></label>
					<input type="number" name="multiplier" min="1" step="0.5" value="<?php echo esc_attr( $settings['multiplier'] ); ?>" class="widefat" />
					<span class="description"><?php esc_html_e( 'Order enough to reach threshold × this value.', 'wp-pos-plugin' ); ?></span>
				</div>
				<div class="simple-pos-form-row" style="margin-top:8px">
					<label><?php esc_html_e( 'Minimum order qty per product', 'wp-pos-plugin' ); ?></label>
					<input type="number" name="min_qty" min="1" step="1" value="<?php echo esc_attr( $settings['min_qty'] ); ?>" class="widefat" />
				</div>
				<div class="simple-pos-form-row" style="margin-top:8px">
					<label><?php esc_html_e( 'Default supplier', 'wp-pos-plugin' ); ?></label>
					<select name="supplier_id" class="widefat">
						<option value="0"><?php esc_html_e( '— none —', 'wp-pos-plugin' ); ?></option>
						<?php foreach ( $suppliers as $s ) : ?>
							<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( (int) $settings['supplier_id'], (int) $s->id ); ?>><?php echo esc_html( $s->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="simple-pos-form-row" style="margin-top:8px">
					<label><?php esc_html_e( 'Notify email (optional)', 'wp-pos-plugin' ); ?></label>
					<input type="email" name="notify_email" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" class="widefat" />
				</div>
				<div class="simple-pos-form-actions" style="margin-top:10px">
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'wp-pos-plugin' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
