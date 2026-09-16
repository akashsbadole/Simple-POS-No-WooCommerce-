<?php
/**
 * Offline mode settings screen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$enabled  = SOM_Offline::is_enabled();
$msg      = isset( $_GET['som_msg'] ) ? sanitize_key( $_GET['som_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Offline Queue', 'wp-pos-plugin' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card">
				<div class="simple-pos-card-head"><h2 class="simple-pos-section-title"><?php esc_html_e( 'How it works', 'wp-pos-plugin' ); ?></h2></div>
				<div class="inside" style="padding:16px">
					<ul style="list-style:disc inside;margin:0;line-height:2">
						<li><?php esc_html_e( 'When enabled, the terminal queues sales if the browser loses its network connection.', 'wp-pos-plugin' ); ?></li>
						<li><?php esc_html_e( 'Queued sales are stored in the browser&#8217;s localStorage and sync automatically when connectivity returns.', 'wp-pos-plugin' ); ?></li>
						<li><?php esc_html_e( 'The server deduplicates via a client key, so the same sale can never be created twice.', 'wp-pos-plugin' ); ?></li>
						<li><?php esc_html_e( 'While offline, stock is NOT decremented server-side. Expect stock drift until sync completes.', 'wp-pos-plugin' ); ?></li>
					</ul>
				</div>
			</div>

			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head">
					<h2 class="simple-pos-section-title"><?php esc_html_e( 'All queued sales (all terminals)', 'wp-pos-plugin' ); ?></h2>
				</div>
				<p style="padding:16px" class="simple-pos-muted">
					<?php esc_html_e( 'Queued sales live in each browser&#8217;s localStorage. Only that browser can sync or inspect them; server-side visibility is limited until sync completes.', 'wp-pos-plugin' ); ?>
				</p>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Settings', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_offline_toggle' ); ?>
						<input type="hidden" name="action" value="simple_pos_offline_toggle" />
						<div class="simple-pos-form-row">
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Enable offline sale queueing', 'wp-pos-plugin' ); ?>
							</label>
						</div>
						<p class="simple-pos-muted" style="font-size:12px;margin:8px 0 16px"><?php esc_html_e( 'Toggling this flag does not clear already-queued sales in the browser.', 'wp-pos-plugin' ); ?></p>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save', 'wp-pos-plugin' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>