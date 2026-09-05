<?php
/**
 * Sales history admin screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$view_id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( $view_id ) {
	$sale = Simple_POS_Sales::get_sale( $view_id );
	if ( ! $sale ) {
		echo '<div class="wrap"><p>' . esc_html__( 'Sale not found.', 'simple-pos' ) . '</p></div>';
		return;
	}
	$items    = Simple_POS_Sales::get_sale_items( $sale->id );
	$cashier  = get_userdata( $sale->cashier_id );
	$customer = $sale->customer_id ? Simple_POS_Customers::get_customer( $sale->customer_id ) : null;
	$tax_breakdown = !empty($sale->tax_breakdown) ? json_decode($sale->tax_breakdown,true) : array();
	?>
	<div class="wrap simple-pos-wrap">
		<div class="simple-pos-page-header">
			<h1><?php echo esc_html( sprintf( __( 'Sale %s', 'simple-pos' ), $sale->sale_number ) ); ?></h1>
			<div class="simple-pos-page-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-sales' ) ); ?>"><?php esc_html_e( 'Back to Sales', 'simple-pos' ); ?></a>
				<button type="button" class="button button-primary" onclick="window.print()"><?php esc_html_e( 'Print Receipt', 'simple-pos' ); ?></button>
			</div>
		</div>
		<span class="simple-pos-status simple-pos-status-<?php echo esc_attr($sale->status);?>"><?php echo esc_html(ucfirst($sale->status));?></span>

		<div class="simple-pos-card simple-pos-table-card simple-pos-receipt-print" style="margin-top:12px">
			<table class="widefat striped simple-pos-table simple-pos-receipt-meta">
				<tbody>
					<tr><th><?php esc_html_e( 'Date', 'simple-pos' ); ?></th><td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $sale->created_at ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></th><td><?php echo esc_html( $cashier ? $cashier->display_name : '—' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Customer', 'simple-pos' ); ?></th><td><?php echo esc_html( $customer ? $customer->name : __( 'Walk-in', 'simple-pos' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Payment method', 'simple-pos' ); ?></th><td><?php echo esc_html( ucfirst( $sale->payment_method ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Tax country/state', 'simple-pos' ); ?></th><td><?php echo esc_html( ($sale->tax_country?:'—').' / '.($sale->tax_state?:'—') ); ?></td></tr>
					<?php if($tax_breakdown): ?><tr><th><?php esc_html_e('Tax breakdown','simple-pos');?></th><td><?php foreach($tax_breakdown as $b) echo esc_html($b['name'].' '.$b['rate'].'% '.Simple_POS_DB::format_currency($b['amount'])).'<br>';?></td></tr><?php endif;?>
				</tbody>
			</table>
		</div>

		<div class="simple-pos-card simple-pos-table-card" style="margin-top:12px">
			<table class="wp-list-table widefat striped simple-pos-table simple-pos-totals-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Qty', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Price', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Tax', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Line Total', 'simple-pos' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item->product_name ); ?></td>
							<td><code><?php echo esc_html( $item->sku ); ?></code></td>
							<td class="num"><?php echo esc_html( $item->qty ); ?></td>
							<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $item->price ) ); ?></td>
							<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $item->tax_amount ) ); ?></td>
							<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $item->line_total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr><th colspan="5" style="text-align:right"><?php esc_html_e( 'Subtotal', 'simple-pos' ); ?></th><td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->subtotal ) ); ?></td></tr>
					<tr><th colspan="5" style="text-align:right"><?php esc_html_e( 'Discount', 'simple-pos' ); ?></th><td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->discount_amount ) ); ?></td></tr>
					<tr><th colspan="5" style="text-align:right"><?php esc_html_e( 'Tax', 'simple-pos' ); ?></th><td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->tax_amount ) ); ?></td></tr>
					<tr><th colspan="5" style="text-align:right"><?php esc_html_e( 'Total', 'simple-pos' ); ?></th><td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td></tr>
				</tfoot>
			</table>
		</div>

		<?php if ( 'completed' === $sale->status && current_user_can( 'void_pos_sales' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-card" onsubmit="return confirm('<?php echo esc_js( __( 'Void this sale and restore stock? This cannot be undone.', 'simple-pos' ) ); ?>');">
				<?php wp_nonce_field( 'simple_pos_void_sale' ); ?>
				<input type="hidden" name="action" value="simple_pos_void_sale" />
				<input type="hidden" name="sale_id" value="<?php echo esc_attr( $sale->id ); ?>" />
				<div style="display:flex;gap:8px;align-items:center">
					<input type="text" name="note" placeholder="<?php esc_attr_e( 'Reason (optional)', 'simple-pos' ); ?>" style="flex:1" />
					<button type="submit" class="button"><?php esc_html_e( 'Void Sale', 'simple-pos' ); ?></button>
				</div>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return;
}

$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-7 days' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'any'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged      = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$result      = Simple_POS_Sales::get_sales( array(
	'date_from' => $date_from,
	'date_to'   => $date_to,
	'status'    => $status,
	'per_page'  => 20,
	'page'      => $paged,
) );
$total_pages = (int) ceil( $result['total'] / 20 );
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Sales History', 'simple-pos' ); ?></h1>
		<div class="simple-pos-page-actions">
			<form method="get" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="simple-pos-inline-form">
				<?php wp_nonce_field('simple_pos_export_sales');?><input type="hidden" name="action" value="simple_pos_export_sales"/>
				<input type="hidden" name="date_from" value="<?php echo esc_attr($date_from);?>"/><input type="hidden" name="date_to" value="<?php echo esc_attr($date_to);?>"/>
				<button class="button" type="submit"><?php esc_html_e('Export CSV','simple-pos');?></button>
			</form>
		</div>
	</div>

	<div class="simple-pos-card simple-pos-filter-bar">
		<form method="get" class="simple-pos-filters">
			<input type="hidden" name="page" value="simple-pos-sales" />
			<div class="simple-pos-filter-field">
				<label for="sales-from"><?php esc_html_e( 'From', 'simple-pos' ); ?></label>
				<input id="sales-from" type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
			</div>
			<div class="simple-pos-filter-field">
				<label for="sales-to"><?php esc_html_e( 'To', 'simple-pos' ); ?></label>
				<input id="sales-to" type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
			</div>
			<div class="simple-pos-filter-field">
				<label for="sales-status"><?php esc_html_e( 'Status', 'simple-pos' ); ?></label>
				<select id="sales-status" name="status">
					<option value="any" <?php selected( $status, 'any' ); ?>><?php esc_html_e( 'Any status', 'simple-pos' ); ?></option>
					<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'simple-pos' ); ?></option>
					<option value="voided" <?php selected( $status, 'voided' ); ?>><?php esc_html_e( 'Voided', 'simple-pos' ); ?></option>
				</select>
			</div>
			<div class="simple-pos-filter-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'simple-pos' ); ?></button>
			</div>
		</form>
	</div>

	<div class="simple-pos-card simple-pos-table-card">
		<table class="wp-list-table widefat striped simple-pos-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Sale #', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Date', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr><td colspan="7" class="simple-pos-empty"><?php esc_html_e( 'No sales found for this range.', 'simple-pos' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $result['items'] as $sale ) : ?>
						<?php $cashier = get_userdata( $sale->cashier_id ); ?>
						<tr>
							<td><code><?php echo esc_html( $sale->sale_number ); ?></code></td>
							<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $sale->created_at ) ); ?></td>
							<td><?php echo esc_html( $cashier ? $cashier->display_name : '—' ); ?></td>
							<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $sale->payment_method ) ); ?></td>
							<td><span class="simple-pos-status simple-pos-status-<?php echo esc_attr($sale->status);?>"><?php echo esc_html( ucfirst( $sale->status ) ); ?></span></td>
							<td class="simple-pos-row-actions"><a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-sales&view=' . $sale->id ) ); ?>"><?php esc_html_e( 'View', 'simple-pos' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post( paginate_links( array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $total_pages,
			) ) );
			?>
		</div></div>
	<?php endif; ?>
</div>
