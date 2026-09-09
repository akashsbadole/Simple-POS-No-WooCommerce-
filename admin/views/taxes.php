<?php if ( ! defined( 'ABSPATH' ) ) exit;
$classes = Simple_POS_Tax::get_classes();
$rates = Simple_POS_Tax::get_rates();
$edit_rate_id = isset($_GET['edit_rate']) ? (int)$_GET['edit_rate'] : 0;
$edit_rate = $edit_rate_id ? Simple_POS_Tax::get_rate($edit_rate_id) : null;
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e('Taxes — per-country classes & rates','wp-pos-plugin');?></h1>
	</div>
	<p class="description"><?php esc_html_e('Set ISO country codes (US, IN, DE, FR, GB...) + optional state (CA, MH). Priority low runs first; compound applies on top of prior taxes. Inclusive = price already contains tax.','wp-pos-plugin');?></p>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e('Tax Classes','wp-pos-plugin');?></h2>
				<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
					<thead><tr><th><?php esc_html_e('Name','wp-pos-plugin');?></th><th><?php esc_html_e('Slug','wp-pos-plugin');?></th><th><?php esc_html_e('Description','wp-pos-plugin');?></th><th><?php esc_html_e('Action','wp-pos-plugin');?></th></tr></thead>
					<tbody>
					<?php if(empty($classes)):?><tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e('No tax classes yet.','wp-pos-plugin');?></td></tr><?php endif;?>
					<?php foreach($classes as $c): ?>
						<tr><td><strong><?php echo esc_html($c->name);?></strong></td><td><code><?php echo esc_html($c->slug);?></code></td><td><?php echo esc_html($c->description);?></td>
						<td class="simple-pos-row-actions"><a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_tax_class&id='.$c->id),'simple_pos_delete_tax_class'));?>" onclick="return confirm('<?php echo esc_js(__('Delete tax class?','wp-pos-plugin'));?>')"><?php esc_html_e('Delete','wp-pos-plugin');?></a></td></tr>
					<?php endforeach;?>
					</tbody>
				</table>
			</div>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="simple-pos-card simple-pos-filters">
				<?php wp_nonce_field('simple_pos_save_tax_class');?>
				<input type="hidden" name="action" value="simple_pos_save_tax_class" />
				<div class="simple-pos-filter-field simple-pos-filter-grow">
					<label><?php esc_html_e('Class name','wp-pos-plugin');?></label>
					<input type="text" name="name" placeholder="<?php esc_attr_e('e.g. Standard','wp-pos-plugin');?>" required />
				</div>
				<div class="simple-pos-filter-field simple-pos-filter-grow">
					<label><?php esc_html_e('Description','wp-pos-plugin');?></label>
					<input type="text" name="description" placeholder="<?php esc_attr_e('Description','wp-pos-plugin');?>" />
				</div>
				<div class="simple-pos-filter-actions">
					<button class="button button-primary" type="submit"><?php esc_html_e('Add Class','wp-pos-plugin');?></button>
				</div>
			</form>

			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e('Tax Rates','wp-pos-plugin');?></h2>
				<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
					<thead><tr><th><?php esc_html_e('Class','wp-pos-plugin');?></th><th><?php esc_html_e('Country','wp-pos-plugin');?></th><th><?php esc_html_e('State','wp-pos-plugin');?></th><th class="num"><?php esc_html_e('Rate %','wp-pos-plugin');?></th><th><?php esc_html_e('Name','wp-pos-plugin');?></th><th><?php esc_html_e('Inc','wp-pos-plugin');?></th><th><?php esc_html_e('Comp','wp-pos-plugin');?></th><th class="num"><?php esc_html_e('Prio','wp-pos-plugin');?></th><th><?php esc_html_e('Actions','wp-pos-plugin');?></th></tr></thead>
					<tbody>
					<?php foreach($rates as $r): ?>
						<tr>
							<td><code><?php echo esc_html($r->class_slug ?? $r->class_id);?></code></td>
							<td><?php echo esc_html($r->country_code);?></td>
							<td><?php echo esc_html($r->state_code ?: '—');?></td>
							<td class="num"><?php echo esc_html($r->rate);?>%</td>
							<td><?php echo esc_html($r->name);?></td>
							<td><?php echo $r->is_inclusive?'✓':'—';?></td>
							<td><?php echo $r->is_compound?'✓':'—';?></td>
							<td class="num"><?php echo esc_html($r->priority);?></td>
							<td class="simple-pos-row-actions">
								<a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-taxes&edit_rate='.$r->id));?>"><?php esc_html_e('Edit','wp-pos-plugin');?></a>
								<a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_tax_rate&id='.$r->id),'simple_pos_delete_tax_rate'));?>" onclick="return confirm('<?php echo esc_js(__('Delete?','wp-pos-plugin'));?>')"><?php esc_html_e('Del','wp-pos-plugin');?></a>
							</td>
						</tr>
					<?php endforeach;?>
					<?php if(empty($rates)):?><tr><td colspan="9" class="simple-pos-empty"><?php esc_html_e('No rates. Seeded US/IN/EU defaults exist after activation — if empty, deactivate/reactivate or check DB.','wp-pos-plugin');?></td></tr><?php endif;?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php echo $edit_rate? esc_html__('Edit Rate','wp-pos-plugin'):esc_html__('Add Rate','wp-pos-plugin');?></span></h2><div class="inside">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
					<?php wp_nonce_field('simple_pos_save_tax_rate');?>
					<input type="hidden" name="action" value="simple_pos_save_tax_rate" />
					<input type="hidden" name="rate_id" value="<?php echo esc_attr($edit_rate->id ?? 0);?>" />
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Class','wp-pos-plugin');?> <span class="required" aria-hidden="true">*</span></label>
						<select name="class_id" class="widefat" required>
							<?php foreach($classes as $c): ?><option value="<?php echo esc_attr($c->id);?>" <?php selected($edit_rate->class_id ?? 0,$c->id);?>><?php echo esc_html($c->name);?></option><?php endforeach;?>
						</select>
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Country','wp-pos-plugin');?> <span class="required" aria-hidden="true">*</span> <span class="description"><?php esc_html_e('(ISO2 or * for all)','wp-pos-plugin');?></span></label>
						<?php
						$stored_country = strtoupper( trim( (string) ( $edit_rate->country_code ?? '' ) ) );
						$country_opts   = Simple_POS_Tax::country_list();
						$rate_country   = $edit_rate ? $stored_country : strtoupper( (string) Simple_POS_Settings::get( 'tax_country', 'US' ) );
						?>
						<select name="country_code" required class="widefat">
							<option value="*"><?php esc_html_e('* — all countries','wp-pos-plugin');?></option>
							<?php if ( $stored_country && '*' !== $stored_country && ! isset( $country_opts[ $stored_country ] ) ) : ?>
								<option value="<?php echo esc_attr( $stored_country ); ?>" selected><?php echo esc_html( $stored_country ); ?></option>
							<?php endif; ?>
							<?php foreach ( $country_opts as $cc => $cname ) : ?>
								<option value="<?php echo esc_attr( $cc ); ?>" <?php selected( $rate_country, $cc ); ?>><?php echo esc_html( $cc . ' — ' . $cname ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('State','wp-pos-plugin');?> <span class="description"><?php esc_html_e('(optional)','wp-pos-plugin');?></span></label>
						<input type="text" name="state_code" value="<?php echo esc_attr($edit_rate->state_code ?? '');?>" class="widefat" placeholder="CA, MH" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Rate %','wp-pos-plugin');?> <span class="required" aria-hidden="true">*</span></label>
						<input type="number" step="0.0001" min="0" max="100" name="rate" required value="<?php echo esc_attr($edit_rate->rate ?? '');?>" class="widefat" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Name','wp-pos-plugin');?></label>
						<input type="text" name="name" value="<?php echo esc_attr($edit_rate->name ?? '');?>" class="widefat" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Priority','wp-pos-plugin');?></label>
						<input type="number" name="priority" value="<?php echo esc_attr($edit_rate->priority ?? 0);?>" class="widefat" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label class="simple-pos-checkbox"><input type="checkbox" name="is_inclusive" value="1" <?php checked($edit_rate->is_inclusive ?? 0,1);?> /> <?php esc_html_e('Inclusive (price contains tax)','wp-pos-plugin');?></label>
					</div>
				<div class="simple-pos-form-row" style="margin-top:8px">
					<label class="simple-pos-checkbox"><input type="checkbox" name="is_compound" value="1" <?php checked($edit_rate->is_compound ?? 0,1);?> /> <?php esc_html_e('Compound (on top of prior taxes)','wp-pos-plugin');?></label>
				</div>
				<div class="simple-pos-form-row" style="margin-top:8px">
					<label class="simple-pos-checkbox"><input type="checkbox" name="gst_split" value="1" <?php checked($edit_rate->gst_split ?? 0,1);?> /> <?php esc_html_e('GST split (50/50 CGST+SGST for intrastate, IGST for interstate)','wp-pos-plugin');?></label>
				</div>
					<div class="simple-pos-form-actions" style="margin-top:10px">
						<button class="button button-primary" type="submit"><?php echo $edit_rate? esc_html__('Update','wp-pos-plugin'):esc_html__('Add','wp-pos-plugin');?> <?php esc_html_e('Rate','wp-pos-plugin');?></button>
						<?php if($edit_rate):?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-taxes'));?>"><?php esc_html_e('Cancel','wp-pos-plugin');?></a><?php endif;?>
					</div>
				</form>
			</div></div>
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php esc_html_e('Examples','wp-pos-plugin');?></span></h2><div class="inside"><p class="simple-pos-muted"><?php esc_html_e('US-CA 7.25% Standard | IN 18% GST | DE 19% VAT | FR reduced 5.5% | Zero * 0%','wp-pos-plugin');?><br><br><?php esc_html_e('Compound: e.g. Canada PST 7% compound on top of GST 5% — set GST prio 0, PST prio 1 compound=1.','wp-pos-plugin');?></p></div></div>
		</div>
	</div>
</div>
