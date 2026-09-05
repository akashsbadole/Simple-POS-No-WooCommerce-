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
		<h1><?php echo esc_html( sprintf( __( 'Sale %s', 'simple-pos' ), $sale->sale_number ) ); ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-sales' ) ); ?>">&larr; <?php esc_html_e( 'Back to Sales History', 'simple-pos' ); ?></a></p>

		<div class="simple-pos-receipt-print">
		<table class="widefat fixed striped simple-pos-receipt-meta">
			<tbody>
				<tr><th><?php esc_html_e( 'Date', 'simple-pos' ); ?></th><td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $sale->created_at ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></th><td><?php echo esc_html( $cashier ? $cashier->display_name : '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Customer', 'simple-pos' ); ?></th><td><?php echo esc_html( $customer ? $customer->name : __( 'Walk-in', 'simple-pos' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Payment method', 'simple-pos' ); ?></th><td><?php echo esc_html( ucfirst( $sale->payment_method ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th><td><?php echo esc_html( ucfirst( $sale->status ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Tax country/state', 'simple-pos' ); ?></th><td><?php echo esc_html( ($sale->tax_country?:'—').' / '.($sale->tax_state?:'—') ); ?></td></tr>
				<?php if($tax_breakdown): ?><tr><th><?php esc_html_e('Tax breakdown','simple-pos');?></th><td><?php foreach($tax_breakdown as $b) echo esc_html($b['name'].' '.$b['rate'].'% '.Simple_POS_DB::format_currency($b['amount'])).'<br>';?></td></tr><?php endif;?>
			</tbody>
		</table>

		<table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Price', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Tax', 'simple-pos' ); ?></th>
					<th><?php esc_html_e( 'Line Total', 'simple-pos' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item->product_name ); ?></td>
						<td><?php echo esc_html( $item->sku ); ?></td>
						<td><?php echo esc_html( $item->qty ); ?></td>
						<td><?php echo esc_html( Simple_POS_DB::format_currency( $item->price ) ); ?></td>
						<td><?php echo esc_html( Simple_POS_DB::format_currency( $item->tax_amount ) ); ?></td>
						<td><?php echo esc_html( Simple_POS_DB::format_currency( $item->line_total ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr><th colspan="5" style="text-align:right;"><?php esc_html_e( 'Subtotal', 'simple-pos' ); ?></th><th><?php echo esc_html( Simple_POS_DB::format_currency( $sale->subtotal ) ); ?></th></tr>
				<tr><th colspan="5" style="text-align:right;"><?php esc_html_e( 'Discount', 'simple-pos' ); ?></th><th><?php echo esc_html( Simple_POS_DB::format_currency( $sale->discount_amount ) ); ?></th></tr>
				<tr><th colspan="5" style="text-align:right;"><?php esc_html_e( 'Tax', 'simple-pos' ); ?></th><th><?php echo esc_html( Simple_POS_DB::format_currency( $sale->tax_amount ) ); ?></th></tr>
				<tr><th colspan="5" style="text-align:right;"><?php esc_html_e( 'Total', 'simple-pos' ); ?></th><th><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></th></tr>
			</tfoot>
		</table>
		</div>

		<?php if ( 'completed' === $sale->status && current_user_can( 'void_pos_sales' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;" onsubmit="return confirm('<?php echo esc_js( __( 'Void this sale and restore stock? This cannot be undone.', 'simple-pos' ) ); ?>');">
				<?php wp_nonce_field( 'simple_pos_void_sale' ); ?>
				<input type="hidden" name="action" value="simple_pos_void_sale" />
				<input type="hidden" name="sale_id" value="<?php echo esc_attr( $sale->id ); ?>" />
				<input type="text" name="note" placeholder="<?php esc_attr_e( 'Reason (optional)', 'simple-pos' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Void Sale', 'simple-pos' ); ?></button>
			</form>
		<?php endif; ?>

		<p><button type="button" class="button" onclick="window.print()"><?php esc_html_e( 'Print Receipt', 'simple-pos' ); ?></button></p>
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
	<h1><?php esc_html_e( 'Sales History', 'simple-pos' ); ?></h1>
	<form method="get" style="display:inline" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
		<?php wp_nonce_field('simple_pos_export_sales');?><input type="hidden" name="action" value="simple_pos_export_sales"/>
		<input type="hidden" name="date_from" value="<?php echo esc_attr($date_from);?>"/><input type="hidden" name="date_to" value="<?php echo esc_attr($date_to);?>"/>
		<button class="button" type="submit">Export CSV</button>
	</form>

	<form method="get" class="simple-pos-filters">
		<input type="hidden" name="page" value="simple-pos-sales" />
		<label><?php esc_html_e( 'From', 'simple-pos' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" /></label>
		<label><?php esc_html_e( 'To', 'simple-pos' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" /></label>
		<select name="status">
			<option value="any" <?php selected( $status, 'any' ); ?>><?php esc_html_e( 'Any status', 'simple-pos' ); ?></option>
			<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'simple-pos' ); ?></option>
			<option value="voided" <?php selected( $status, 'voided' ); ?>><?php esc_html_e( 'Voided', 'simple-pos' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'simple-pos' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Sale #', 'simple-pos' ); ?></th>
				<th><?php esc_html_e( 'Date', 'simple-pos' ); ?></th>
				<th><?php esc_html_e( 'Cashier', 'simple-pos' ); ?></th>
				<th><?php esc_html_e( 'Total', 'simple-pos' ); ?></th>
				<th><?php esc_html_e( 'Payment', 'simple-pos' ); ?></th>
				<th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No sales found for this range.', 'simple-pos' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $sale ) : ?>
					<?php $cashier = get_userdata( $sale->cashier_id ); ?>
					<tr>
						<td><?php echo esc_html( $sale->sale_number ); ?></td>
						<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $sale->created_at ) ); ?></td>
						<td><?php echo esc_html( $cashier ? $cashier->display_name : '—' ); ?></td>
						<td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $sale->payment_method ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $sale->status ) ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-sales&view=' . $sale->id ) ); ?>"><?php esc_html_e( 'View', 'simple-pos' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

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
