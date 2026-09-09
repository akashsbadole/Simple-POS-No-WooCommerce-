<?php
/**
 * Customers admin screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editing_id       = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing_customer = $editing_id ? Simple_POS_Customers::get_customer( $editing_id ) : null;
$search           = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged            = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$result      = Simple_POS_Customers::get_customers( $search, 20, $paged );
$total_pages = (int) ceil( $result['total'] / 20 );

$history = $editing_customer ? Simple_POS_Customers::get_purchase_history( $editing_customer->id, 10 ) : array();
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Customers', 'wp-pos-plugin' ); ?></h1>
	</div>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">

			<div class="simple-pos-card simple-pos-filter-bar">
				<form method="get" class="simple-pos-filters">
					<input type="hidden" name="page" value="simple-pos-customers" />
					<div class="simple-pos-filter-field simple-pos-filter-grow">
						<label for="cust-s"><?php esc_html_e( 'Search', 'wp-pos-plugin' ); ?></label>
						<input id="cust-s" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, phone, email…', 'wp-pos-plugin' ); ?>" />
					</div>
					<div class="simple-pos-filter-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'wp-pos-plugin' ); ?></button>
					</div>
				</form>
			</div>

			<div class="simple-pos-card simple-pos-table-card">
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'wp-pos-plugin' ); ?></th>
							<th><?php esc_html_e( 'Email', 'wp-pos-plugin' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wp-pos-plugin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $result['items'] ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No customers found.', 'wp-pos-plugin' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $result['items'] as $customer ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $customer->name ); ?></strong></td>
									<td><?php echo esc_html( $customer->phone ); ?></td>
									<td><?php echo esc_html( $customer->email ); ?></td>
									<td class="simple-pos-row-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-customers&edit=' . $customer->id ) ); ?>"><?php esc_html_e( 'Edit', 'wp-pos-plugin' ); ?></a>
										<a class="delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_customer&id=' . $customer->id ), 'simple_pos_delete_customer' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this customer?', 'wp-pos-plugin' ) ); ?>');"><?php esc_html_e( 'Delete', 'wp-pos-plugin' ); ?></a>
									</td>
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

			<?php if ( $editing_customer && $history ) : ?>
				<div class="simple-pos-card simple-pos-table-card">
					<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e( 'Purchase History', 'wp-pos-plugin' ); ?></h2>
					<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Sale #', 'wp-pos-plugin' ); ?></th>
								<th><?php esc_html_e( 'Date', 'wp-pos-plugin' ); ?></th>
								<th class="num"><?php esc_html_e( 'Total', 'wp-pos-plugin' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-pos-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $sale ) : ?>
								<tr>
									<td><code><?php echo esc_html( $sale->sale_number ); ?></code></td>
									<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $sale->created_at ) ); ?></td>
									<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td>
									<td><span class="simple-pos-status simple-pos-status-<?php echo esc_attr($sale->status);?>"><?php echo esc_html( ucfirst( $sale->status ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php echo $editing_customer ? esc_html__( 'Edit Customer', 'wp-pos-plugin' ) : esc_html__( 'Add Customer', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'simple_pos_save_customer' ); ?>
						<input type="hidden" name="action" value="simple_pos_save_customer" />
						<input type="hidden" name="customer_id" value="<?php echo esc_attr( $editing_customer ? $editing_customer->id : 0 ); ?>" />

						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input type="text" name="name" required value="<?php echo esc_attr( $editing_customer->name ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Phone', 'wp-pos-plugin' ); ?></label>
							<input type="text" name="phone" value="<?php echo esc_attr( $editing_customer->phone ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Email', 'wp-pos-plugin' ); ?></label>
							<input type="email" name="email" value="<?php echo esc_attr( $editing_customer->email ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Address', 'wp-pos-plugin' ); ?></label>
							<textarea name="address" class="widefat" rows="3"><?php echo esc_textarea( $editing_customer->address ?? '' ); ?></textarea>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Notes', 'wp-pos-plugin' ); ?></label>
							<textarea name="notes" class="widefat" rows="3"><?php echo esc_textarea( $editing_customer->notes ?? '' ); ?></textarea>
						</div>
						<div class="simple-pos-form-actions" style="margin-top:10px">
							<button type="submit" class="button button-primary"><?php echo $editing_customer ? esc_html__( 'Update Customer', 'wp-pos-plugin' ) : esc_html__( 'Add Customer', 'wp-pos-plugin' ); ?></button>
							<?php if ( $editing_customer ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-customers' ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-pos-plugin' ); ?></a>
							<?php endif; ?>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
