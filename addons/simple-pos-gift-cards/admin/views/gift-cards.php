<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
/**
 * Gift cards admin screen: list, create, top up.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$cards = Simple_POS_Sgc_Gift_Cards::get_cards();
$msg   = isset( $_GET['sgc_msg'] ) ? sanitize_key( $_GET['sgc_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Gift Cards', 'simple-pos' ); ?></h1>
	</div>

	<?php if ( 'created' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gift card created.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'topup' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Balance topped up.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'invalid' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Code and a positive amount are required.', 'simple-pos' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head"><h2 class="simple-pos-section-title"><?php esc_html_e( 'Cards', 'simple-pos' ); ?></h2></div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr>
						<th><?php esc_html_e( 'Code', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Balance', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Created', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Top up', 'simple-pos' ); ?></th>
					</tr></thead>
					<tbody>
						<?php if ( empty( $cards ) ) : ?>
							<tr><td colspan="5" class="simple-pos-empty"><?php esc_html_e( 'No gift cards yet.', 'simple-pos' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $cards as $c ) : ?>
							<tr>
								<td><code><?php echo esc_html( $c->code ); ?></code></td>
								<td class="num"><strong><?php echo esc_html( Simple_POS_DB::format_currency( $c->balance ) ); ?></strong></td>
								<td><span class="simple-pos-status simple-pos-status-<?php echo esc_attr( $c->status ); ?>"><?php echo esc_html( $c->status ); ?></span></td>
								<td><?php echo esc_html( mysql2date( 'M j, Y', $c->created_at ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-inline-form">
										<?php wp_nonce_field( 'simple_pos_giftcard_topup' ); ?>
										<input type="hidden" name="action" value="simple_pos_giftcard_topup" />
										<input type="hidden" name="card_id" value="<?php echo esc_attr( $c->id ); ?>" />
										<input type="number" name="amount" step="0.01" min="1" required placeholder="0.00" style="width:80px" />
										<button class="button" type="submit"><?php esc_html_e( 'Add', 'simple-pos' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Create Card', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_giftcard_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_giftcard_save" />
						<div class="simple-pos-form-row">
							<label for="sgc-code"><?php esc_html_e( 'Code', 'simple-pos' ); ?></label>
							<input id="sgc-code" type="text" name="code" required pattern="[A-Za-z0-9-]{4,}" class="widefat" placeholder="GC-VIP-2024" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sgc-amount"><?php esc_html_e( 'Initial balance', 'simple-pos' ); ?></label>
							<input id="sgc-amount" type="number" name="amount" step="0.01" min="0.01" required class="widefat" />
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Create', 'simple-pos' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>