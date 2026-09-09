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
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'POS Dashboard', 'wp-pos-plugin' ); ?></h1>
		<div class="simple-pos-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-terminal' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open Terminal', 'wp-pos-plugin' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-reports' ) ); ?>" class="button"><?php esc_html_e( 'Full Reports', 'wp-pos-plugin' ); ?></a>
		</div>
	</div>

	<div class="simple-pos-stat-cards">
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( "Today's Revenue", 'wp-pos-plugin' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['revenue'] ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Sales Today', 'wp-pos-plugin' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( $summary['sale_count'] ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Items Sold', 'wp-pos-plugin' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( $summary['items_sold'] ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Avg. Sale', 'wp-pos-plugin' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['avg_sale'] ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Gross Profit', 'wp-pos-plugin' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['gross_profit'] ) ); ?></span>
		</div>
	</div>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0 0 10px;padding:0"><?php esc_html_e( 'Recent Sales', 'wp-pos-plugin' ); ?></h2>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sale #', 'wp-pos-plugin' ); ?></th>
							<th><?php esc_html_e( 'Time', 'wp-pos-plugin' ); ?></th>
							<th class="num"><?php esc_html_e( 'Total', 'wp-pos-plugin' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-pos-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $recent['items'] ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No sales yet.', 'wp-pos-plugin' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $recent['items'] as $sale ) : ?>
								<tr>
									<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-sales&view=' . $sale->id ) ); ?>"><?php echo esc_html( $sale->sale_number ); ?></a></strong></td>
									<td><?php echo esc_html( mysql2date( 'M j, g:i a', $sale->created_at ) ); ?></td>
									<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td>
									<td><span class="simple-pos-status simple-pos-status-<?php echo esc_attr($sale->status);?>"><?php echo esc_html( ucfirst( $sale->status ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Low Stock', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<?php if ( empty( $low_stock ) ) : ?>
						<p class="simple-pos-muted"><?php esc_html_e( 'All products are well stocked.', 'wp-pos-plugin' ); ?></p>
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
