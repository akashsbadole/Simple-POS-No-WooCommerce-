<?php
/**
 * Add-ons screen: what's installed (self-reported by add-on plugins) and
 * what's available to buy.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bundled_addons = Simple_POS_Addons::get_bundled();
$addon_catalog  = Simple_POS_Addons::get_catalog();
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Add-ons', 'simple-pos' ); ?></h1>
	</div>
	<p class="description"><?php esc_html_e( 'All add-ons are free and included with Simple POS — enable or disable them below. Changes apply on the next page load.', 'simple-pos' ); ?></p>
	<?php if ( isset( $_GET['simple_pos_addons_msg'] ) && 'updated' === sanitize_key( wp_unslash( $_GET['simple_pos_addons_msg'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Add-on setting saved.', 'simple-pos' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-card simple-pos-table-card">
		<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e( 'Included add-ons', 'simple-pos' ); ?></h2>
		<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
			<thead><tr><th><?php esc_html_e( 'Name', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Version', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Description', 'simple-pos' ); ?></th><th style="width:170px"><?php esc_html_e( 'Status', 'simple-pos' ); ?></th></tr></thead>
			<tbody>
				<?php if ( empty( $bundled_addons ) ) : ?>
					<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No add-ons bundled yet.', 'simple-pos' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $bundled_addons as $addon ) : ?>
					<?php
					$addon_slug = isset( $addon['slug'] ) ? sanitize_key( $addon['slug'] ) : sanitize_key( $addon['name'] ?? '' );
					$addon_on   = Simple_POS_Addons::is_enabled( $addon_slug );
					?>
					<tr style="<?php echo $addon_on ? '' : 'opacity:.55'; ?>">
						<td><strong><?php echo esc_html( $addon['name'] ?? '' ); ?></strong></td>
						<td><code><?php echo esc_html( $addon['version'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $addon['description'] ?? '' ); ?></td>
						<td>
							<?php if ( $addon_on ) : ?>
								<span style="color:#00a32a;font-weight:600"><?php esc_html_e( 'Enabled', 'simple-pos' ); ?></span>
							<?php else : ?>
								<span style="color:#787c82;font-weight:600"><?php esc_html_e( 'Disabled', 'simple-pos' ); ?></span>
							<?php endif; ?>
							<?php if ( $addon_slug ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:6px">
									<?php wp_nonce_field( 'simple_pos_addon_toggle_' . esc_attr( $addon_slug ) ); ?>
									<input type="hidden" name="action" value="simple_pos_addon_toggle" />
									<input type="hidden" name="addon" value="<?php echo esc_attr( $addon_slug ); ?>" />
									<input type="hidden" name="enable" value="<?php echo $addon_on ? '0' : '1'; ?>" />
									<button type="submit" class="button button-small"><?php echo esc_html( $addon_on ? __( 'Disable', 'simple-pos' ) : __( 'Enable', 'simple-pos' ) ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<h2 class="simple-pos-section-title" style="margin-top:16px"><?php esc_html_e( 'Available add-ons', 'simple-pos' ); ?></h2>
	<?php
	$badge_colors = array(
		'New'         => '#00a32a',
		'Popular'     => '#dba617',
		'Coming soon' => '#787c82',
	);
	?>
	<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
		<?php foreach ( $addon_catalog as $item ) : ?>
			<div class="simple-pos-card" style="margin:0">
				<h3 style="margin:0 0 6px"><?php echo esc_html( $item['name'] ?? '' ); ?>
					<?php if ( ! empty( $item['badge'] ) ) : ?>
						<span style="display:inline-block;font-size:11px;font-weight:600;color:#fff;background:<?php echo esc_attr( $badge_colors[ $item['badge'] ] ?? '#2271b1' ); ?>;border-radius:10px;padding:1px 8px;vertical-align:middle"><?php echo esc_html( $item['badge'] ); ?></span>
					<?php endif; ?>
				</h3>
				<p class="simple-pos-muted" style="margin:0 0 6px"><?php echo esc_html( $item['description'] ?? '' ); ?></p>
				<p style="margin:0 0 10px;font-weight:600;color:#00a32a"><?php esc_html_e( 'Free', 'simple-pos' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( $item['url'] ?? '#' ); ?>" <?php echo ( isset( $item['url'] ) && '#' !== $item['url'] ) ? 'target="_blank" rel="noopener"' : ''; ?>><?php esc_html_e( 'View add-on', 'simple-pos' ); ?></a>
			</div>
		<?php endforeach; ?>
	</div>
</div>
