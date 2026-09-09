<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Backup', 'wp-pos-plugin' ); ?></h1>
	</div>
	<p class="simple-pos-muted" style="margin:0 0 16px"><?php esc_html_e( 'Download a complete JSON backup of all POS data, or restore from a previous backup. Keep backup files in a safe place.', 'wp-pos-plugin' ); ?></p>
	<div class="simple-pos-card simple-pos-form-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'simple_pos_backup_export' ); ?>
			<input type="hidden" name="action" value="simple_pos_backup_export" />
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Download Backup', 'wp-pos-plugin' ); ?></button>
		</form>
	</div>
	<div class="simple-pos-card simple-pos-form-card" style="margin-top:20px">
		<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e( 'Restore from Backup', 'wp-pos-plugin' ); ?></h2>
		<p class="simple-pos-muted" style="margin:0 0 12px"><?php esc_html_e( 'Upload a JSON backup file to restore. This will replace existing data with the same IDs.', 'wp-pos-plugin' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'simple_pos_backup_import' ); ?>
			<input type="hidden" name="action" value="simple_pos_backup_import" />
			<div class="simple-pos-form-row">
				<label><?php esc_html_e( 'Backup file', 'wp-pos-plugin' ); ?></label>
				<input type="file" name="backup_file" accept=".json" required />
			</div>
			<div class="simple-pos-form-actions" style="margin-top:10px">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Restore Backup', 'wp-pos-plugin' ); ?></button>
			</div>
		</form>
	</div>
</div>
