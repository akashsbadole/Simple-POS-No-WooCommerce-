<?php
/**
 * Reports admin screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$range = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7days'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

switch ( $range ) {
	case 'today':
		$date_from = current_time( 'Y-m-d' );
		$date_to   = current_time( 'Y-m-d' );
		break;
	case '30days':
		$date_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to   = current_time( 'Y-m-d' );
		break;
	case 'month':
		$date_from = current_time( 'Y-m-01' );
		$date_to   = current_time( 'Y-m-d' );
		break;
	case 'custom':
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-7 days' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : current_time( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		break;
	case '7days':
	default:
		$date_from = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$date_to   = current_time( 'Y-m-d' );
		break;
}

$summary      = Simple_POS_Reports::get_summary( $date_from, $date_to );
$by_day       = Simple_POS_Reports::get_sales_by_day( $date_from, $date_to );
$top_products = Simple_POS_Reports::get_top_products( $date_from, $date_to, 10 );
$by_cashier   = Simple_POS_Reports::get_sales_by_cashier( $date_from, $date_to );

$max_revenue = 0;
foreach ( $by_day as $day ) {
	$max_revenue = max( $max_revenue, (float) $day['revenue'] );
}
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Reports', 'simple-pos' ); ?></h1>
	</div>

	<div class="simple-pos-card simple-pos-filter-bar">
		<form method="get" class="simple-pos-filters">
			<input type="hidden" name="page" value="simple-pos-reports" />
			<div class="simple-pos-filter-field">
				<label for="rep-range"><?php esc_html_e( 'Range', 'simple-pos' ); ?></label>
				<select id="rep-range" name="range" onchange="if(this.value!=='custom'){this.form.submit()}">
					<option value="today" <?php selected( $range, 'today' ); ?>><?php esc_html_e( 'Today', 'simple-pos' ); ?></option>
					<option value="7days" <?php selected( $range, '7days' ); ?>><?php esc_html_e( 'Last 7 days', 'simple-pos' ); ?></option>
					<option value="30days" <?php selected( $range, '30days' ); ?>><?php esc_html_e( 'Last 30 days', 'simple-pos' ); ?></option>
					<option value="month" <?php selected( $range, 'month' ); ?>><?php esc_html_e( 'This month', 'simple-pos' ); ?></option>
					<option value="custom" <?php selected( $range, 'custom' ); ?>><?php esc_html_e( 'Custom range', 'simple-pos' ); ?></option>
				</select>
			</div>
			<?php if ( 'custom' === $range ) : ?>
				<div class="simple-pos-filter-field">
					<label for="rep-from"><?php esc_html_e( 'From', 'simple-pos' ); ?></label>
					<input id="rep-from" type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
				</div>
				<div class="simple-pos-filter-field">
					<label for="rep-to"><?php esc_html_e( 'To', 'simple-pos' ); ?></label>
					<input id="rep-to" type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
				</div>
				<div class="simple-pos-filter-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'simple-pos' ); ?></button>
				</div>
			<?php endif; ?>
		</form>
	</div>

	<div class="simple-pos-stat-cards">
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Revenue', 'simple-pos' ); ?></span>
			<span class="simple-pos-stat-value"><?php echo esc_html( Simple_POS_DB::format_currency( $summary['revenue'] ) ); ?></span>
		</div>
		<div class="simple-pos-stat-card">
			<span class="simple-pos-stat-label"><?php esc_html_e( 'Sales', 'simple-pos' ); ?></span>
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

	<div class="simple-pos-card">
		<h2 class="simple-pos-section-title" style="margin:0 0 10px"><?php esc_html_e( 'Revenue by Day', 'simple-pos' ); ?></h2>
		<?php if ( empty( $by_day ) ) : ?>
			<p class="simple-pos-muted"><?php esc_html_e( 'No sales in this range.', 'simple-pos' ); ?></p>
		<?php else : ?>
			<div class="simple-pos-bar-chart">
				<?php foreach ( $by_day as $day ) : ?>
					<?php $pct = $max_revenue > 0 ? round( ( (float) $day['revenue'] / $max_revenue ) * 100 ) : 0; ?>
					<div class="simple-pos-bar-col">
						<div class="simple-pos-bar" style="height: <?php echo esc_attr( max( 2, $pct ) ); ?>%;" title="<?php echo esc_attr( Simple_POS_DB::format_currency( $day['revenue'] ) ); ?>"></div>
						<span class="simple-pos-bar-label"><?php echo esc_html( gmdate( 'M j', strtotime( $day['day'] ) ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e( 'Top Products', 'simple-pos' ); ?></h2>
				<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'simple-pos' ); ?></th>
							<th class="num"><?php esc_html_e( 'Qty Sold', 'simple-pos' ); ?></th>
							<th class="num"><?php esc_html_e( 'Revenue', 'simple-pos' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $top_products ) ) : ?>
							<tr><td colspan="3" class="simple-pos-empty"><?php esc_html_e( 'No sales in this range.', 'simple-pos' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $top_products as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row->product_name ); ?></td>
									<td class="num"><?php echo esc_html( $row->qty_sold ); ?></td>
									<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $row->revenue ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'By Cashier', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<?php if ( empty( $by_cashier ) ) : ?>
						<p class="simple-pos-muted"><?php esc_html_e( 'No sales in this range.', 'simple-pos' ); ?></p>
					<?php else : ?>
						<ul class="simple-pos-category-list">
							<?php foreach ( $by_cashier as $row ) : ?>
								<li><?php echo esc_html( $row->cashier_name ); ?> — <strong><?php echo esc_html( Simple_POS_DB::format_currency( $row->revenue ) ); ?></strong> <span class="simple-pos-muted">(<?php echo esc_html( $row->sale_count ); ?>)</span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
