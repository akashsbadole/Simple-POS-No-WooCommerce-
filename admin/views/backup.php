<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'Backup', 'simple-pos' ); ?></h1>
	</div>
	<p class="simple-pos-muted" style="margin:0 0 16px"><?php esc_html_e( 'Download a complete JSON backup of all POS data. Keep this file in a safe place. CSV exports for products and sales are available on their respective pages.', 'simple-pos' ); ?></p>
	<div class="simple-pos-card simple-pos-form-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'simple_pos_backup_export' ); ?>
			<input type="hidden" name="action" value="simple_pos_backup_export" />
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Download Backup', 'simple-pos' ); ?></button>
		</form>
	</div>
</div>
