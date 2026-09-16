<?php
/**
 * Online orders admin: view and manage incoming orders.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$orders = SOO_Orders::get_orders( 'any' );
$msg    = isset( $_GET['soo_msg'] ) ? sanitize_key( $_GET['soo_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Online Orders', 'wp-pos-plugin' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Updated.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<p class="simple-pos-muted" style="margin:0 0 16px"><?php esc_html_e( 'Incoming orders appear here. Load them at the terminal to process via normal checkout (stock is deducted at that point).', 'wp-pos-plugin' ); ?></p>

	<div class="simple-pos-card simple-pos-table-card">
		<table class="wp-list-table widefat striped simple-pos-table">
			<thead><tr>
				<th><?php esc_html_e( 'Order #', 'wp-pos-plugin' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'wp-pos-plugin' ); ?></th>
				<th><?php esc_html_e( 'Phone', 'wp-pos-plugin' ); ?></th>
				<th class="num"><?php esc_html_e( 'Subtotal', 'wp-pos-plugin' ); ?></th>
				<th><?php esc_html_e( 'Items', 'wp-pos-plugin' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-pos-plugin' ); ?></th>
				<th><?php esc_html_e( 'Placed', 'wp-pos-plugin' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-pos-plugin' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $orders ) ) : ?>
					<tr><td colspan="8" class="simple-pos-empty"><?php esc_html_e( 'No online orders yet.', 'wp-pos-plugin' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $orders as $o ) : ?>
					<?php
					$o_items = json_decode( $o->items, true );
					$o_count = 0;
					if ( is_array( $o_items ) ) {
						foreach ( $o_items as $li ) {
							$o_count += isset( $li['qty'] ) ? (int) $li['qty'] : 0;
						}
					}
					?>
					<tr>
						<td><code><?php echo esc_html( 'ONLINE-' . $o->id ); ?></code></td>
						<td>
							<strong><?php echo esc_html( $o->customer_name ); ?></strong>
							<?php if ( ! empty( $o->customer_email ) ) : ?><br><small><?php echo esc_html( $o->customer_email ); ?></small><?php endif; ?>
							<?php if ( ! empty( $o->delivery_address ) ) : ?><br><small><?php echo esc_html( $o->delivery_address ); ?></small><?php endif; ?>
						</td>
						<td><?php echo esc_html( $o->customer_phone ?: '—' ); ?></td>
						<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $o->subtotal ) ); ?></td>
						<td><?php echo esc_html( $o_count ); ?></td>
						<td>
							<span class="simple-pos-status simple-pos-status-<?php echo esc_attr( $o->status ); ?>">
								<?php echo esc_html( ucfirst( $o->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $o->created_at ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-inline-form">
								<?php wp_nonce_field( 'simple_pos_online_order_status' ); ?>
								<input type="hidden" name="action" value="simple_pos_online_order_status" />
								<input type="hidden" name="order_id" value="<?php echo esc_attr( $o->id ); ?>" />
								<select name="order_status" onchange="this.form.submit()" style="max-width:110px">
									<?php
									$statuses = array( 'pending', 'fulfilled', 'cancelled' );
									foreach ( $statuses as $s ) :
										?>
										<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $o->status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>