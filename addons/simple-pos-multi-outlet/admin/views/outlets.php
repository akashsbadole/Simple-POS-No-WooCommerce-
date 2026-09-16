<?php
/**
 * Outlets screen: list + add/edit + default outlet.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$outlets  = SMO_Outlets::get_outlets();
$settings = SMO_Outlets::get_settings();

$editing_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing    = $editing_id ? SMO_Outlets::get_outlet( $editing_id ) : null;
$msg        = isset( $_GET['smo_msg'] ) ? sanitize_key( $_GET['smo_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Outlets', 'wp-pos-plugin' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-pos-plugin' ); ?></p></div>
	<?php elseif ( 'deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Outlet deleted.', 'wp-pos-plugin' ); ?></p></div>
	<?php elseif ( 'name_required' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Outlet name required.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<p class="simple-pos-muted" style="margin:0 0 16px"><?php esc_html_e( 'Each sale is tagged with its outlet. Stock stays global across outlets.', 'wp-pos-plugin' ); ?></p>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head">
					<h2 class="simple-pos-section-title"><?php esc_html_e( 'Outlets', 'wp-pos-plugin' ); ?></h2>
				</div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr><th><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?></th><th><?php esc_html_e( 'Address', 'wp-pos-plugin' ); ?></th><th><?php esc_html_e( 'Default', 'wp-pos-plugin' ); ?></th><th><?php esc_html_e( 'Actions', 'wp-pos-plugin' ); ?></th></tr></thead>
					<tbody>
						<?php if ( empty( $outlets ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No outlets yet.', 'wp-pos-plugin' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $outlets as $o ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $o->name ); ?></strong></td>
								<td class="simple-pos-muted"><?php echo esc_html( $o->address ?: '—' ); ?></td>
								<td><?php echo ( (int) $settings['default_outlet_id'] === (int) $o->id ) ? '<span class="simple-pos-status simple-pos-status-completed">' . esc_html__( 'Default', 'wp-pos-plugin' ) . '</span>' : '—'; ?></td>
								<td class="simple-pos-row-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-outlets&edit=' . $o->id ) ); ?>"><?php esc_html_e( 'Edit', 'wp-pos-plugin' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_outlet_delete&id=' . $o->id ), 'simple_pos_outlet_delete' ) ); ?>" class="delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this outlet?', 'wp-pos-plugin' ) ); ?>');"><?php esc_html_e( 'Delete', 'wp-pos-plugin' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php echo $editing ? esc_html__( 'Edit Outlet', 'wp-pos-plugin' ) : esc_html__( 'Add Outlet', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_outlet_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_outlet_save" />
						<input type="hidden" name="outlet_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>" />
						<div class="simple-pos-form-row">
							<label for="smo-name"><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input id="smo-name" type="text" name="name" required value="<?php echo esc_attr( $editing->name ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="smo-address"><?php esc_html_e( 'Address', 'wp-pos-plugin' ); ?></label>
							<textarea id="smo-address" name="address" class="widefat" rows="2"><?php echo esc_textarea( $editing->address ?? '' ); ?></textarea>
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php echo $editing ? esc_html__( 'Update Outlet', 'wp-pos-plugin' ) : esc_html__( 'Add Outlet', 'wp-pos-plugin' ); ?></button>
							<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-outlets' ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-pos-plugin' ); ?></a><?php endif; ?>
						</div>
					</form>
				</div>
			</div>

			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Default Outlet', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'simple_pos_outlet_default' ); ?>
						<input type="hidden" name="action" value="simple_pos_outlet_default" />
						<div class="simple-pos-form-row">
							<label for="smo-default"><?php esc_html_e( 'Preselected on the terminal', 'wp-pos-plugin' ); ?></label>
							<select id="smo-default" name="outlet_id" class="widefat">
								<option value="0"><?php esc_html_e( '— none —', 'wp-pos-plugin' ); ?></option>
								<?php foreach ( $outlets as $o ) : ?>
									<option value="<?php echo esc_attr( $o->id ); ?>" <?php selected( (int) $settings['default_outlet_id'], (int) $o->id ); ?>><?php echo esc_html( $o->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'wp-pos-plugin' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
