<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
/**
 * Currencies screen: manage exchange rates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$rates = Simple_POS_Sfx_Rates::get_rates();
$base  = strtoupper( (string) Simple_POS_Settings::get( 'currency_code', 'USD' ) );
$msg   = isset( $_GET['sfx_msg'] ) ? sanitize_key( $_GET['sfx_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Currencies', 'simple-pos' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rate saved.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rate deleted.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'invalid' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Use a 3-letter currency code and a positive rate.', 'simple-pos' ); ?></p></div>
	<?php endif; ?>

	<p class="simple-pos-muted" style="margin:0 0 16px">
		<?php
		/* translators: %s: base currency code. */
		echo esc_html( sprintf( __( 'Rates are expressed as 1 foreign unit = X %s. The terminal shows the foreign amount and records sales in the base currency.', 'simple-pos' ), $base ) );
		?>
	</p>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head"><h2 class="simple-pos-section-title"><?php esc_html_e( 'Rates', 'simple-pos' ); ?></h2></div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr>
						<th><?php esc_html_e( 'Currency', 'simple-pos' ); ?></th>
						<th class="num"><?php esc_html_e( 'Rate to base', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'simple-pos' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th>
					</tr></thead>
					<tbody>
						<?php if ( empty( $rates ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No rates yet.', 'simple-pos' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $rates as $r ) : ?>
							<tr>
								<td><code><?php echo esc_html( $r->currency_code ); ?></code></td>
								<td class="num"><?php echo esc_html( number_format_i18n( (float) $r->rate_to_base, 4 ) ); ?></td>
								<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $r->updated_at ) ); ?></td>
								<td class="simple-pos-row-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-currencies&rate=' . $r->currency_code ) ); ?>"><?php esc_html_e( 'Edit', 'simple-pos' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_fx_delete&code=' . $r->currency_code ), 'simple_pos_fx_delete' ) ); ?>" class="delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this rate?', 'simple-pos' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-pos' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Add Rate', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_fx_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_fx_save" />
						<div class="simple-pos-form-row">
							<label for="sfx-code"><?php esc_html_e( 'Currency code', 'simple-pos' ); ?></label>
							<input id="sfx-code" type="text" name="currency_code" required maxlength="3" pattern="[A-Za-z]{3}" class="widefat" placeholder="USD" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sfx-rate">
								<?php
								/* translators: %s: base currency code. */
								echo esc_html( sprintf( __( 'Units of base (%s) per 1 unit', 'simple-pos' ), $base ) );
								?>
							</label>
							<input id="sfx-rate" type="number" name="rate_to_base" required step="0.000001" min="0.000001" class="widefat" />
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Add Rate', 'simple-pos' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>