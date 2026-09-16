<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
/**
 * Reports Pro screen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-01' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cashier   = isset( $_GET['cashier_id'] ) ? (int) $_GET['cashier_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$xz       = Simple_POS_Spar_Reports::xz( $date_from, $date_to, $cashier );
$hourly   = Simple_POS_Spar_Reports::hourly( $date_from, $date_to );
$payments = Simple_POS_Spar_Reports::payment_mix( $date_from, $date_to, $cashier );
$products = Simple_POS_Spar_Reports::product_mix( $date_from, $date_to, 20 );
$taxes    = Simple_POS_Spar_Reports::tax_summary( $date_from, $date_to, $cashier );
$cashiers = Simple_POS_Reports::get_sales_by_cashier( $date_from, $date_to );

$max_hour = 0;
foreach ( $hourly as $h ) {
	$max_hour = max( $max_hour, $h['revenue'] );
}

$export_url = function ( $type ) use ( $date_from, $date_to, $cashier ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'     => 'simple_pos_adv_reports_export',
				'type'       => $type,
				'date_from'  => $date_from,
				'date_to'    => $date_to,
				'cashier_id' => $cashier,
			),
			admin_url( 'admin-post.php' )
		),
		'simple_pos_adv_reports_export'
	);
};
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Reports Pro', 'simple-pos' ); ?></h1>
	</div>

	<form method="get" class="simple-pos-card simple-pos-filters">
		<input type="hidden" name="page" value="simple-pos-adv-reports" />
		<div class="simple-pos-filter-field">
			<label><?php esc_html_e( 'From', 'simple-pos' ); ?></label>
			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
		</div>
		<div class="simple-pos-filter-field">
			<label><?php esc_html_e( 'To', 'simple-pos' ); ?></label>
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
		</div>
		<div class="simple-pos-filter-field">
			<label><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></label>
			<select name="cashier_id">
				<option value="0"><?php esc_html_e( 'All cashiers', 'simple-pos' ); ?></option>
				<?php foreach ( $cashiers as $c ) : ?>
					<option value="<?php echo esc_attr( $c->cashier_id ); ?>" <?php selected( $cashier, (int) $c->cashier_id ); ?>><?php echo esc_html( $c->cashier_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="simple-pos-filter-actions">
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Apply', 'simple-pos' ); ?></button>
		</div>
	</form>

	<div class="simple-pos-card simple-pos-table-card">
		<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0">
			<?php esc_html_e( 'X / Z summary', 'simple-pos' ); ?>
			<a class="button" style="float:right" href="<?php echo esc_url( $export_url( 'xz' ) ); ?>"><?php esc_html_e( 'CSV', 'simple-pos' ); ?></a>
		</h2>
		<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
			<tbody>
				<tr><td><strong><?php esc_html_e( 'Sales', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( $xz['sale_count'] ); ?></td><td><strong><?php esc_html_e( 'Items sold', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( $xz['items_sold'] ); ?></td></tr>
				<tr><td><strong><?php esc_html_e( 'Gross', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( Simple_POS_DB::format_currency( $xz['gross'] ) ); ?></td><td><strong><?php esc_html_e( 'Discount', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( Simple_POS_DB::format_currency( $xz['discount'] ) ); ?></td></tr>
				<tr><td><strong><?php esc_html_e( 'Tax', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( Simple_POS_DB::format_currency( $xz['tax'] ) ); ?></td><td><strong><?php esc_html_e( 'Net', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( Simple_POS_DB::format_currency( $xz['net'] ) ); ?></td></tr>
				<tr><td><strong><?php esc_html_e( 'Avg basket', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( Simple_POS_DB::format_currency( $xz['avg_basket'] ) ); ?></td><td><strong><?php esc_html_e( 'Gross profit', 'simple-pos' ); ?></strong></td><td><?php echo esc_html( Simple_POS_DB::format_currency( $xz['gross_profit'] ) ); ?></td></tr>
			</tbody>
		</table>
	</div>

	<div class="simple-pos-card simple-pos-table-card">
		<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0">
			<?php esc_html_e( 'Hourly sales', 'simple-pos' ); ?>
			<a class="button" style="float:right" href="<?php echo esc_url( $export_url( 'hourly' ) ); ?>"><?php esc_html_e( 'CSV', 'simple-pos' ); ?></a>
		</h2>
		<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
			<thead><tr><th><?php esc_html_e( 'Hour', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Sales', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Revenue', 'simple-pos' ); ?></th><th style="width:40%">&nbsp;</th></tr></thead>
			<tbody>
				<?php foreach ( $hourly as $h ) : ?>
					<tr>
						<td><?php echo esc_html( sprintf( '%02d:00', $h['hr'] ) ); ?></td>
						<td class="num"><?php echo esc_html( $h['sale_count'] ); ?></td>
						<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $h['revenue'] ) ); ?></td>
						<td><div style="background:#2271b1;height:8px;border-radius:4px;width:<?php echo esc_attr( $max_hour > 0 ? round( $h['revenue'] / $max_hour * 100 ) : 0 ); ?>%"></div></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="simple-pos-card simple-pos-table-card">
		<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0">
			<?php esc_html_e( 'Payment mix', 'simple-pos' ); ?>
			<a class="button" style="float:right" href="<?php echo esc_url( $export_url( 'payments' ) ); ?>"><?php esc_html_e( 'CSV', 'simple-pos' ); ?></a>
		</h2>
		<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
			<thead><tr><th><?php esc_html_e( 'Payment method', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Sales', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Revenue', 'simple-pos' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $payments ) ) : ?><tr><td colspan="3" class="simple-pos-empty"><?php esc_html_e( 'No sales in this range.', 'simple-pos' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $payments as $p ) : ?>
					<tr><td><?php echo esc_html( ucfirst( $p->payment_method ) ); ?></td><td class="num"><?php echo esc_html( $p->sale_count ); ?></td><td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $p->revenue ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="simple-pos-card simple-pos-table-card">
		<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0">
			<?php esc_html_e( 'Product mix (top 20)', 'simple-pos' ); ?>
			<a class="button" style="float:right" href="<?php echo esc_url( $export_url( 'products' ) ); ?>"><?php esc_html_e( 'CSV', 'simple-pos' ); ?></a>
		</h2>
		<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
			<thead><tr><th><?php esc_html_e( 'Product', 'simple-pos' ); ?></th><th><?php esc_html_e( 'SKU', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Qty', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Revenue', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Profit', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Margin', 'simple-pos' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $products ) ) : ?><tr><td colspan="6" class="simple-pos-empty"><?php esc_html_e( 'No sales in this range.', 'simple-pos' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $products as $p ) : ?>
					<tr>
						<td><?php echo esc_html( $p->product_name ); ?></td>
						<td><code><?php echo esc_html( $p->sku ?: '—' ); ?></code></td>
						<td class="num"><?php echo esc_html( $p->qty ); ?></td>
						<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $p->revenue ) ); ?></td>
						<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $p->profit ) ); ?></td>
						<td class="num"><?php echo esc_html( $p->margin . '%' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="simple-pos-card simple-pos-table-card">
		<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0">
			<?php esc_html_e( 'Tax summary', 'simple-pos' ); ?>
			<a class="button" style="float:right" href="<?php echo esc_url( $export_url( 'taxes' ) ); ?>"><?php esc_html_e( 'CSV', 'simple-pos' ); ?></a>
		</h2>
		<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
			<thead><tr><th><?php esc_html_e( 'Tax', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Rate', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'simple-pos' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $taxes ) ) : ?><tr><td colspan="3" class="simple-pos-empty"><?php esc_html_e( 'No tax collected in this range.', 'simple-pos' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $taxes as $t ) : ?>
					<tr><td><?php echo esc_html( $t['name'] ); ?></td><td class="num"><?php echo esc_html( $t['rate'] . '%' ); ?></td><td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $t['amount'] ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
