<?php
/**
 * Dashboard admin screen — quick daily overview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$today   = current_time( 'Y-m-d' );
$summary = Simple_POS_Reports::get_summary( $today, $today );
$low_stock = Simple_POS_Products::get_low_stock_products( 8 );
$recent  = Simple_POS_Sales::get_sales( array( 'per_page' => 8, 'page' => 1 ) );
?>
<div class="wrap simple-pos-wrap">
	<h1><?php esc_html_e( 'POS Dashboard', 'simple-pos' ); ?></h1>

	<div class="simple-pos-stat-cards">
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( "Today's Revenue", 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['revenue'] ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Sales Today', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( $summary['sale_count'] ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Items Sold', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( $summary['items_sold'] ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Avg. Sale', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['avg_sale'] ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Gross Profit', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['gross_profit'] ) ); ?></span>
		</div>
	</div>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-terminal' ) ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Open Terminal', 'simple-pos' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-reports' ) ); ?>" class="button button-hero"><?php esc_html_e( 'Full Reports', 'simple-pos' ); ?></a>
	</p>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<h2><?php esc_html_e( 'Recent Sales', 'simple-pos' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Sale #', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Time', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Total', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent['items'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No sales yet.', 'simple-pos' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent['items'] as $sale ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-sales&view=' . $sale->id ) ); ?>"><?php echo esc_html( $sale->sale_number ); ?></a></td>
								<td><?php echo esc_html( mysql2date( 'M j, g:i a', $sale->created_at ) ); ?></td>
								<td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $sale->status ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Low Stock', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<?php if ( empty( $low_stock ) ) : ?>
						<p><?php esc_html_e( 'All products are well stocked.', 'simple-pos' ); ?></p>
					<?php else : ?>
						<ul class="simple-pos-category-list">
							<?php foreach ( $low_stock as $product ) : ?>
								<li>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products&edit=' . $product->id ) ); ?>"><?php echo esc_html( $product->name ); ?></a>
									<span class="simple-pos-low-stock"><?php echo esc_html( $product->stock_qty ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
