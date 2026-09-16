<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
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
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Customers', 'simple-pos' ); ?></h1>
	</div>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">

			<div class="simple-pos-card simple-pos-filter-bar">
				<form method="get" class="simple-pos-filters">
					<input type="hidden" name="page" value="simple-pos-customers" />
					<div class="simple-pos-filter-field simple-pos-filter-grow">
						<label for="cust-s"><?php esc_html_e( 'Search', 'simple-pos' ); ?></label>
						<input id="cust-s" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, phone, email…', 'simple-pos' ); ?>" />
					</div>
					<div class="simple-pos-filter-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'simple-pos' ); ?></button>
					</div>
				</form>
			</div>

			<div class="simple-pos-card simple-pos-table-card">
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'simple-pos' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'simple-pos' ); ?></th>
							<th><?php esc_html_e( 'Email', 'simple-pos' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $result['items'] ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No customers found.', 'simple-pos' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $result['items'] as $customer ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $customer->name ); ?></strong></td>
									<td><?php echo esc_html( $customer->phone ); ?></td>
									<td><?php echo esc_html( $customer->email ); ?></td>
									<td class="simple-pos-row-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-customers&edit=' . $customer->id ) ); ?>"><?php esc_html_e( 'Edit', 'simple-pos' ); ?></a>
										<a class="delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_customer&id=' . $customer->id ), 'simple_pos_delete_customer' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this customer?', 'simple-pos' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-pos' ); ?></a>
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
					<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e( 'Purchase History', 'simple-pos' ); ?></h2>
					<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Sale #', 'simple-pos' ); ?></th>
								<th><?php esc_html_e( 'Date', 'simple-pos' ); ?></th>
								<th class="num"><?php esc_html_e( 'Total', 'simple-pos' ); ?></th>
								<th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th>
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
				<h2 class="hndle"><span><?php echo $editing_customer ? esc_html__( 'Edit Customer', 'simple-pos' ) : esc_html__( 'Add Customer', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'simple_pos_save_customer' ); ?>
						<input type="hidden" name="action" value="simple_pos_save_customer" />
						<input type="hidden" name="customer_id" value="<?php echo esc_attr( $editing_customer ? $editing_customer->id : 0 ); ?>" />

						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Name', 'simple-pos' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input type="text" name="name" required value="<?php echo esc_attr( $editing_customer->name ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Phone', 'simple-pos' ); ?></label>
							<input type="text" name="phone" value="<?php echo esc_attr( $editing_customer->phone ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Email', 'simple-pos' ); ?></label>
							<input type="email" name="email" value="<?php echo esc_attr( $editing_customer->email ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Address', 'simple-pos' ); ?></label>
							<textarea name="address" class="widefat" rows="3"><?php echo esc_textarea( $editing_customer->address ?? '' ); ?></textarea>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e( 'Notes', 'simple-pos' ); ?></label>
							<textarea name="notes" class="widefat" rows="3"><?php echo esc_textarea( $editing_customer->notes ?? '' ); ?></textarea>
						</div>
						<div class="simple-pos-form-actions" style="margin-top:10px">
							<button type="submit" class="button button-primary"><?php echo $editing_customer ? esc_html__( 'Update Customer', 'simple-pos' ) : esc_html__( 'Add Customer', 'simple-pos' ); ?></button>
							<?php if ( $editing_customer ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-customers' ) ); ?>"><?php esc_html_e( 'Cancel', 'simple-pos' ); ?></a>
							<?php endif; ?>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
