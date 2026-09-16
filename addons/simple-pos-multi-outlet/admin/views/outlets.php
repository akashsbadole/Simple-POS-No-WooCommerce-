<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
/**
 * Outlets screen: list + add/edit + default outlet.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$outlets  = Simple_POS_Smo_Outlets::get_outlets();
$settings = Simple_POS_Smo_Outlets::get_settings();

$editing_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing    = $editing_id ? Simple_POS_Smo_Outlets::get_outlet( $editing_id ) : null;
$msg        = isset( $_GET['smo_msg'] ) ? sanitize_key( $_GET['smo_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Outlets', 'simple-pos' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Outlet deleted.', 'simple-pos' ); ?></p></div>
	<?php elseif ( 'name_required' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Outlet name required.', 'simple-pos' ); ?></p></div>
	<?php endif; ?>

	<p class="simple-pos-muted" style="margin:0 0 16px"><?php esc_html_e( 'Each sale is tagged with its outlet. Stock stays global across outlets.', 'simple-pos' ); ?></p>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head">
					<h2 class="simple-pos-section-title"><?php esc_html_e( 'Outlets', 'simple-pos' ); ?></h2>
				</div>
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr><th><?php esc_html_e( 'Name', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Address', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Default', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th></tr></thead>
					<tbody>
						<?php if ( empty( $outlets ) ) : ?>
							<tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e( 'No outlets yet.', 'simple-pos' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $outlets as $o ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $o->name ); ?></strong></td>
								<td class="simple-pos-muted"><?php echo esc_html( $o->address ?: '—' ); ?></td>
								<td><?php echo ( (int) $settings['default_outlet_id'] === (int) $o->id ) ? '<span class="simple-pos-status simple-pos-status-completed">' . esc_html__( 'Default', 'simple-pos' ) . '</span>' : '—'; ?></td>
								<td class="simple-pos-row-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-outlets&edit=' . $o->id ) ); ?>"><?php esc_html_e( 'Edit', 'simple-pos' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_outlet_delete&id=' . $o->id ), 'simple_pos_outlet_delete' ) ); ?>" class="delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this outlet?', 'simple-pos' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-pos' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php echo $editing ? esc_html__( 'Edit Outlet', 'simple-pos' ) : esc_html__( 'Add Outlet', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_outlet_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_outlet_save" />
						<input type="hidden" name="outlet_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>" />
						<div class="simple-pos-form-row">
							<label for="smo-name"><?php esc_html_e( 'Name', 'simple-pos' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input id="smo-name" type="text" name="name" required value="<?php echo esc_attr( $editing->name ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="smo-address"><?php esc_html_e( 'Address', 'simple-pos' ); ?></label>
							<textarea id="smo-address" name="address" class="widefat" rows="2"><?php echo esc_textarea( $editing->address ?? '' ); ?></textarea>
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php echo $editing ? esc_html__( 'Update Outlet', 'simple-pos' ) : esc_html__( 'Add Outlet', 'simple-pos' ); ?></button>
							<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-outlets' ) ); ?>"><?php esc_html_e( 'Cancel', 'simple-pos' ); ?></a><?php endif; ?>
						</div>
					</form>
				</div>
			</div>

			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e( 'Default Outlet', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'simple_pos_outlet_default' ); ?>
						<input type="hidden" name="action" value="simple_pos_outlet_default" />
						<div class="simple-pos-form-row">
							<label for="smo-default"><?php esc_html_e( 'Preselected on the terminal', 'simple-pos' ); ?></label>
							<select id="smo-default" name="outlet_id" class="widefat">
								<option value="0"><?php esc_html_e( '— none —', 'simple-pos' ); ?></option>
								<?php foreach ( $outlets as $o ) : ?>
									<option value="<?php echo esc_attr( $o->id ); ?>" <?php selected( (int) $settings['default_outlet_id'], (int) $o->id ); ?>><?php echo esc_html( $o->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'simple-pos' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
