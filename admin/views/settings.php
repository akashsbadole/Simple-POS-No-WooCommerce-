<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
if ( ! defined( 'ABSPATH' ) ) exit;
$settings = Simple_POS_Settings::get_all();
$currency_list = Simple_POS_Settings::currency_list();
$tax_classes = class_exists('Simple_POS_Tax') ? Simple_POS_Tax::get_classes() : array();
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e( 'POS Settings', 'simple-pos' ); ?></h1>
		<div class="simple-pos-page-actions">
			<button type="submit" form="simple-pos-settings-form" class="button button-primary"><?php esc_html_e( 'Save Settings', 'simple-pos' ); ?></button>
		</div>
	</div>
	<form id="simple-pos-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-settings-form">
		<?php wp_nonce_field( 'simple_pos_save_settings' ); ?>
		<input type="hidden" name="action" value="simple_pos_save_settings" />
		<div class="simple-pos-columns">
			<div class="simple-pos-col-main">
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e('Store','simple-pos');?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><label for="store_name"><?php esc_html_e( 'Store name', 'simple-pos' ); ?></label></th><td><input type="text" id="store_name" name="store_name" value="<?php echo esc_attr( $settings['store_name'] ); ?>" class="regular-text" /></td></tr>
						<tr><th><label for="store_address"><?php esc_html_e( 'Address', 'simple-pos' ); ?></label></th><td><textarea id="store_address" name="store_address" rows="2" class="large-text"><?php echo esc_textarea( $settings['store_address'] ); ?></textarea></td></tr>
						<tr><th><label for="store_phone"><?php esc_html_e( 'Phone', 'simple-pos' ); ?></label></th><td><input type="text" id="store_phone" name="store_phone" value="<?php echo esc_attr( $settings['store_phone'] ); ?>" class="regular-text" /></td></tr>
						<tr><th><label for="store_email"><?php esc_html_e( 'Email', 'simple-pos' ); ?></label></th><td><input type="email" id="store_email" name="store_email" value="<?php echo esc_attr( $settings['store_email'] ); ?>" class="regular-text" /></td></tr>
						<tr><th><label for="store_gstin"><?php esc_html_e( 'GSTIN', 'simple-pos' ); ?></label></th><td><input type="text" id="store_gstin" name="store_gstin" value="<?php echo esc_attr( $settings['store_gstin'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e('e.g. 27AABCT1234R1ZX','simple-pos');?></span></td></tr>
					</table>
				</div>
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e('Currency','simple-pos');?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><label for="currency_code"><?php esc_html_e('Base currency','simple-pos');?></label></th>
							<td><select id="currency_code" name="currency_code">
								<?php foreach($currency_list as $code=>$info): ?>
									<option value="<?php echo esc_attr($code);?>" <?php selected($settings['currency_code'],$code);?>><?php echo esc_html($code.' — '.$info['name'].' ('.$info['symbol'].')');?></option>
								<?php endforeach;?>
							</select>
							<p class="description"><?php esc_html_e('Single store-wide currency. Symbol can be overridden below.','simple-pos');?></p></td></tr>
						<tr><th><label for="currency_symbol"><?php esc_html_e( 'Currency symbol', 'simple-pos' ); ?></label></th><td><input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" class="small-text" /> <span class="description"><?php esc_html_e('Leave empty to use default.','simple-pos');?></span></td></tr>
						<tr><th><label for="currency_position"><?php esc_html_e( 'Symbol position', 'simple-pos' ); ?></label></th><td><select id="currency_position" name="currency_position"><option value="before" <?php selected( $settings['currency_position'], 'before' ); ?>><?php esc_html_e( 'Before ($10.00)', 'simple-pos' ); ?></option><option value="after" <?php selected( $settings['currency_position'], 'after' ); ?>><?php esc_html_e( 'After (10.00$)', 'simple-pos' ); ?></option></select></td></tr>
						<tr><th><label for="decimal_places"><?php esc_html_e( 'Decimal places', 'simple-pos' ); ?></label></th><td><input type="number" id="decimal_places" name="decimal_places" min="0" max="4" value="<?php echo esc_attr( $settings['decimal_places'] ); ?>" class="small-text" /></td></tr>
					</table>
				</div>
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e('Inventory','simple-pos');?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><label for="low_stock_threshold"><?php esc_html_e( 'Default low stock threshold', 'simple-pos' ); ?></label></th><td><input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0" value="<?php echo esc_attr( $settings['low_stock_threshold'] ); ?>" class="small-text" /></td></tr>
						<tr><th><?php esc_html_e( 'Allow overselling', 'simple-pos' ); ?></th><td><label class="simple-pos-checkbox"><input type="checkbox" id="allow_negative_stock" name="allow_negative_stock" value="1" <?php checked( $settings['allow_negative_stock'], 1 ); ?> /> <?php esc_html_e( 'Allow checkout even if stock would go negative', 'simple-pos' ); ?></label></td></tr>
						<tr><th><label for="sale_number_prefix"><?php esc_html_e( 'Sale number prefix', 'simple-pos' ); ?></label></th><td><input type="text" id="sale_number_prefix" name="sale_number_prefix" value="<?php echo esc_attr( $settings['sale_number_prefix'] ); ?>" class="small-text" /></td></tr>
						<tr><th><label for="po_number_prefix"><?php esc_html_e( 'PO number prefix', 'simple-pos' ); ?></label></th><td><input type="text" id="po_number_prefix" name="po_number_prefix" value="<?php echo esc_attr( $settings['po_number_prefix'] ); ?>" class="small-text" /></td></tr>
					</table>
				</div>
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e('Printing & Barcode','simple-pos');?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><label for="paper_width"><?php esc_html_e('Paper width','simple-pos');?></label></th><td><select id="paper_width" name="paper_width"><option value="58mm" <?php selected($settings['paper_width'],'58mm');?>><?php esc_html_e('58mm','simple-pos');?></option><option value="80mm" <?php selected($settings['paper_width'],'80mm');?>><?php esc_html_e('80mm','simple-pos');?></option></select></td></tr>
						<tr><th><label for="printer_type"><?php esc_html_e('Printer','simple-pos');?></label></th><td><select id="printer_type" name="printer_type"><option value="browser" <?php selected($settings['printer_type'],'browser');?>><?php esc_html_e('Browser print','simple-pos');?></option><option value="usb" <?php selected($settings['printer_type'],'usb');?>><?php esc_html_e('USB ESC/POS (WebUSB)','simple-pos');?></option><option value="network" <?php selected($settings['printer_type'],'network');?>><?php esc_html_e('Network ESC/POS','simple-pos');?></option></select></td></tr>
						<tr><th><label for="network_printer_ip"><?php esc_html_e('Network printer IP','simple-pos');?></label></th><td><input type="text" id="network_printer_ip" name="network_printer_ip" value="<?php echo esc_attr($settings['network_printer_ip']);?>" class="regular-text" placeholder="192.168.1.50:9100" /></td></tr>
						<tr><th><?php esc_html_e('Cash drawer','simple-pos');?></th><td><label class="simple-pos-checkbox"><input type="checkbox" name="auto_kick_drawer" value="1" <?php checked($settings['auto_kick_drawer'],1);?> /> <?php esc_html_e('Auto kick drawer on sale complete','simple-pos');?></label></td></tr>
						<tr><th><label for="barcode_symbology"><?php esc_html_e('Barcode symbology','simple-pos');?></label></th><td><select id="barcode_symbology" name="barcode_symbology"><option value="CODE128" <?php selected($settings['barcode_symbology'],'CODE128');?>>CODE128</option><option value="EAN13" <?php selected($settings['barcode_symbology'],'EAN13');?>>EAN13</option><option value="QR" <?php selected($settings['barcode_symbology'],'QR');?>>QR</option></select></td></tr>
						<tr><th><label for="barcode_label_format"><?php esc_html_e('Label sheet','simple-pos');?></label></th><td><select id="barcode_label_format" name="barcode_label_format"><option value="a4_30" <?php selected($settings['barcode_label_format'],'a4_30');?>><?php esc_html_e('A4 30-up (Avery 5160)','simple-pos');?></option><option value="a4_65" <?php selected($settings['barcode_label_format'],'a4_65');?>><?php esc_html_e('A4 65-up','simple-pos');?></option><option value="roll_58" <?php selected($settings['barcode_label_format'],'roll_58');?>><?php esc_html_e('Roll 58mm','simple-pos');?></option><option value="roll_80" <?php selected($settings['barcode_label_format'],'roll_80');?>><?php esc_html_e('Roll 80mm','simple-pos');?></option></select></td></tr>
					</table>
				</div>
			</div>
			<div class="simple-pos-col-side">
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e('Tax defaults','simple-pos');?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><label for="tax_country"><?php esc_html_e('Default tax country','simple-pos');?></label></th><td><input type="text" id="tax_country" name="tax_country" value="<?php echo esc_attr($settings['tax_country']);?>" class="small-text" placeholder="US" /> <span class="description"><?php esc_html_e('ISO2 e.g. US, IN','simple-pos');?></span></td></tr>
						<tr><th><label for="tax_state"><?php esc_html_e('Default state','simple-pos');?></label></th><td><input type="text" id="tax_state" name="tax_state" value="<?php echo esc_attr($settings['tax_state']);?>" class="small-text" placeholder="CA" /> <span class="description"><?php esc_html_e('e.g. CA, MH','simple-pos');?></span></td></tr>
						<tr><th><label for="default_tax_class_id"><?php esc_html_e('Default tax class','simple-pos');?></label></th><td><select id="default_tax_class_id" name="default_tax_class_id">
							<?php foreach($tax_classes as $tc): ?><option value="<?php echo esc_attr($tc->id);?>" <?php selected($settings['default_tax_class_id'],$tc->id);?>><?php echo esc_html($tc->name);?></option><?php endforeach;?>
						</select></td></tr>
						<tr><th><?php esc_html_e('Tax inclusive pricing','simple-pos');?></th><td><label class="simple-pos-checkbox"><input type="checkbox" name="tax_inclusive" value="1" <?php checked($settings['tax_inclusive'],1);?> /> <?php esc_html_e('Prices include tax','simple-pos');?></label></td></tr>
						<tr><th><?php esc_html_e('Discount before tax','simple-pos');?></th><td><label class="simple-pos-checkbox"><input type="checkbox" name="discount_before_tax" value="1" <?php checked($settings['discount_before_tax'],1);?> /> <?php esc_html_e('Apply discount before tax','simple-pos');?></label></td></tr>
						<tr><th><label for="tax_rounding"><?php esc_html_e('Tax rounding','simple-pos');?></label></th><td><select id="tax_rounding" name="tax_rounding"><option value="line" <?php selected($settings['tax_rounding'],'line');?>><?php esc_html_e('Per line','simple-pos');?></option><option value="total" <?php selected($settings['tax_rounding'],'total');?>><?php esc_html_e('Per order','simple-pos');?></option></select></td></tr>
						<tr><th><label for="default_tax_rate"><?php esc_html_e( 'Legacy tax rate (%)', 'simple-pos' ); ?></label></th><td><input type="number" id="default_tax_rate" name="default_tax_rate" min="0" step="0.01" value="<?php echo esc_attr( $settings['default_tax_rate'] ); ?>" class="small-text" /><p class="description"><?php esc_html_e( 'Fallback only; prefer tax classes.', 'simple-pos' ); ?></p></td></tr>
					</table>
				</div>
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e('Receipt','simple-pos');?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><label for="receipt_header"><?php esc_html_e( 'Header', 'simple-pos' ); ?></label></th><td><textarea id="receipt_header" name="receipt_header" rows="3" class="large-text"><?php echo esc_textarea( $settings['receipt_header'] ); ?></textarea></td></tr>
						<tr><th><label for="receipt_footer"><?php esc_html_e( 'Footer', 'simple-pos' ); ?></label></th><td><textarea id="receipt_footer" name="receipt_footer" rows="3" class="large-text"><?php echo esc_textarea( $settings['receipt_footer'] ); ?></textarea></td></tr>
					</table>
				</div>
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e( 'Advanced', 'simple-pos' ); ?></h2>
					<table class="form-table" style="margin:0">
						<tr><th><?php esc_html_e( 'Uninstall', 'simple-pos' ); ?></th><td><label class="simple-pos-checkbox"><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'], 1 ); ?> /> <?php esc_html_e( 'Delete all POS data when plugin is deleted', 'simple-pos' ); ?></label></td></tr>
					</table>
				</div>
				<div class="simple-pos-card simple-pos-form-card">
					<h2 class="simple-pos-section-title" style="margin-top:0"><?php esc_html_e( 'Staff Roles', 'simple-pos' ); ?></h2>
					<p class="simple-pos-muted" style="margin:0 0 8px"><?php esc_html_e( 'Assign under Users:', 'simple-pos' ); ?></p>
					<ul class="simple-pos-category-list" style="margin:0">
						<li><strong><?php esc_html_e('POS Cashier','simple-pos');?></strong> — <?php esc_html_e('terminal + view sales','simple-pos');?></li>
						<li><strong><?php esc_html_e('POS Manager','simple-pos');?></strong> — <?php esc_html_e('full POS + reports, voids, taxes, POs','simple-pos');?></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="simple-pos-card simple-pos-form-card" style="text-align:right">
			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Settings', 'simple-pos' ); ?></button>
		</div>
	</form>
</div>
