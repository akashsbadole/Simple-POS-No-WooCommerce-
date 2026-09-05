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
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Customers', 'simple-pos' ); ?></h1>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">

			<form method="get" class="simple-pos-filters">
				<input type="hidden" name="page" value="simple-pos-customers" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, phone, email…', 'simple-pos' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'simple-pos' ); ?></button>
			</form>

			<table class="wp-list-table widefat fixed striped">
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
						<tr><td colspan="4"><?php esc_html_e( 'No customers found.', 'simple-pos' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $customer ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $customer->name ); ?></strong></td>
								<td><?php echo esc_html( $customer->phone ); ?></td>
								<td><?php echo esc_html( $customer->email ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-customers&edit=' . $customer->id ) ); ?>"><?php esc_html_e( 'Edit', 'simple-pos' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_customer&id=' . $customer->id ), 'simple_pos_delete_customer' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this customer?', 'simple-pos' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-pos' ); ?></a>
								</td>
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

			<?php if ( $editing_customer && $history ) : ?>
				<h2><?php esc_html_e( 'Purchase History', 'simple-pos' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sale #', 'simple-pos' ); ?></th>
							<th><?php esc_html_e( 'Date', 'simple-pos' ); ?></th>
							<th><?php esc_html_e( 'Total', 'simple-pos' ); ?></th>
							<th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history as $sale ) : ?>
							<tr>
								<td><?php echo esc_html( $sale->sale_number ); ?></td>
								<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $sale->created_at ) ); ?></td>
								<td><?php echo esc_html( Simple_POS_DB::format_currency( $sale->total ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $sale->status ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

		</div>

		<div class="simple-pos-col-side">
			<div class="postbox">
				<h2 class="hndle"><span><?php echo $editing_customer ? esc_html__( 'Edit Customer', 'simple-pos' ) : esc_html__( 'Add Customer', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'simple_pos_save_customer' ); ?>
						<input type="hidden" name="action" value="simple_pos_save_customer" />
						<input type="hidden" name="customer_id" value="<?php echo esc_attr( $editing_customer ? $editing_customer->id : 0 ); ?>" />

						<p>
							<label><?php esc_html_e( 'Name', 'simple-pos' ); ?> *</label>
							<input type="text" name="name" required value="<?php echo esc_attr( $editing_customer->name ?? '' ); ?>" class="widefat" />
						</p>
						<p>
							<label><?php esc_html_e( 'Phone', 'simple-pos' ); ?></label>
							<input type="text" name="phone" value="<?php echo esc_attr( $editing_customer->phone ?? '' ); ?>" class="widefat" />
						</p>
						<p>
							<label><?php esc_html_e( 'Email', 'simple-pos' ); ?></label>
							<input type="email" name="email" value="<?php echo esc_attr( $editing_customer->email ?? '' ); ?>" class="widefat" />
						</p>
						<p>
							<label><?php esc_html_e( 'Address', 'simple-pos' ); ?></label>
							<textarea name="address" class="widefat" rows="3"><?php echo esc_textarea( $editing_customer->address ?? '' ); ?></textarea>
						</p>
						<p>
							<label><?php esc_html_e( 'Notes', 'simple-pos' ); ?></label>
							<textarea name="notes" class="widefat" rows="3"><?php echo esc_textarea( $editing_customer->notes ?? '' ); ?></textarea>
						</p>
						<p>
							<button type="submit" class="button button-primary"><?php echo $editing_customer ? esc_html__( 'Update Customer', 'simple-pos' ) : esc_html__( 'Add Customer', 'simple-pos' ); ?></button>
							<?php if ( $editing_customer ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-customers' ) ); ?>"><?php esc_html_e( 'Cancel', 'simple-pos' ); ?></a>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
