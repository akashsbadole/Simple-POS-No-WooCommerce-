<?php
/**
 * Tables admin screen: floor plan with status colors.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$grouped = STS_Tables::get_by_status();
$editing_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$editing    = $editing_id ? STS_Tables::get_table( $editing_id ) : null;
$msg        = isset( $_GET['sts_msg'] ) ? sanitize_key( $_GET['sts_msg'] ) : '';
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Tables', 'wp-pos-plugin' ); ?></h1>
	</div>

	<?php if ( 'saved' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'wp-pos-plugin' ); ?></p></div>
	<?php elseif ( 'deleted' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Table deleted.', 'wp-pos-plugin' ); ?></p></div>
	<?php endif; ?>

	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<div class="simple-pos-card-head">
					<h2 class="simple-pos-section-title"><?php esc_html_e( 'Floor', 'wp-pos-plugin' ); ?></h2>
				</div>
				<div style="padding:16px">
					<?php foreach ( $grouped as $status => $tables ) : ?>
						<h3 style="margin:16px 0 8px;text-transform:uppercase;font-size:12px;letter-spacing:1px;color:var(--pos-muted,#666)">
							<?php echo esc_html( ucfirst( $status ) ); ?>
							<span style="color:var(--pos-muted,#999)">(<?php echo count( $tables ); ?>)</span>
						</h3>
						<div class="simple-pos-floor" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px">
							<?php if ( empty( $tables ) ) : ?>
								<p class="simple-pos-muted"><?php esc_html_e( 'No tables.', 'wp-pos-plugin' ); ?></p>
							<?php endif; ?>
							<?php foreach ( $tables as $t ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-tables&edit=' . $t->id ) ); ?>"
								   class="simple-pos-table-tile"
								   style="display:block;padding:16px;border-radius:8px;text-align:center;text-decoration:none;font-weight:600;border:2px solid <?php echo 'occupied' === $t->status ? 'var(--pos-primary,#1e73be)' : 'var(--pos-border,#ddd)'; ?>;background:<?php echo 'occupied' === $t->status ? 'var(--pos-primary,#1e73be)' : 'var(--pos-surface,#f9f9f9)'; ?>;color:<?php echo 'occupied' === $t->status ? '#fff' : 'inherit'; ?>">
									<?php echo esc_html( $t->name ); ?>
									<div style="font-size:11px;margin-top:4px;opacity:0.8"><?php echo esc_html( $t->seats ); ?> seats</div>
									<?php if ( $t->active_sale_id ) : ?>
										<div style="font-size:10px;margin-top:4px;opacity:0.7">#<?php echo esc_html( $t->active_sale_id ); ?></div>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php echo $editing ? esc_html__( 'Edit Table', 'wp-pos-plugin' ) : esc_html__( 'Add Table', 'wp-pos-plugin' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-stack">
						<?php wp_nonce_field( 'simple_pos_table_save' ); ?>
						<input type="hidden" name="action" value="simple_pos_table_save" />
						<input type="hidden" name="table_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>" />
						<div class="simple-pos-form-row">
							<label for="sts-name"><?php esc_html_e( 'Name', 'wp-pos-plugin' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input id="sts-name" type="text" name="name" required value="<?php echo esc_attr( $editing->name ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label for="sts-seats"><?php esc_html_e( 'Seats', 'wp-pos-plugin' ); ?></label>
							<input id="sts-seats" type="number" name="seats" min="1" value="<?php echo esc_attr( $editing->seats ?? 4 ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-actions">
							<button class="button button-primary" type="submit"><?php echo $editing ? esc_html__( 'Update', 'wp-pos-plugin' ) : esc_html__( 'Add Table', 'wp-pos-plugin' ); ?></button>
							<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-tables' ) ); ?>"><?php esc_html_e( 'Cancel', 'wp-pos-plugin' ); ?></a><?php endif; ?>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
