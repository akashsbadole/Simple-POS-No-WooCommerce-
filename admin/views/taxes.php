<?php if ( ! defined( 'ABSPATH' ) ) exit;
$classes = Simple_POS_Tax::get_classes();
$rates = Simple_POS_Tax::get_rates();
$edit_rate_id = isset($_GET['edit_rate']) ? (int)$_GET['edit_rate'] : 0;
$edit_rate = $edit_rate_id ? Simple_POS_Tax::get_rate($edit_rate_id) : null;
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e('Taxes — per-country classes & rates','simple-pos');?></h1>
	</div>
	<p class="description"><?php esc_html_e('Set IS country codes (US, IN, DE, FR, GB...) + optional state (CA, MH). Priority low runs first; compound applies on top of prior taxes. Inclusive = price already contains tax.','simple-pos');?></p>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e('Tax Classes','simple-pos');?></h2>
				<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
					<thead><tr><th><?php esc_html_e('Name','simple-pos');?></th><th><?php esc_html_e('Slug','simple-pos');?></th><th><?php esc_html_e('Description','simple-pos');?></th><th><?php esc_html_e('Action','simple-pos');?></th></tr></thead>
					<tbody>
					<?php if(empty($classes)):?><tr><td colspan="4" class="simple-pos-empty"><?php esc_html_e('No tax classes yet.','simple-pos');?></td></tr><?php endif;?>
					<?php foreach($classes as $c): ?>
						<tr><td><strong><?php echo esc_html($c->name);?></strong></td><td><code><?php echo esc_html($c->slug);?></code></td><td><?php echo esc_html($c->description);?></td>
						<td class="simple-pos-row-actions"><a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_tax_class&id='.$c->id),'simple_pos_delete_tax_class'));?>" onclick="return confirm('<?php echo esc_js(__('Delete tax class?','simple-pos'));?>')"><?php esc_html_e('Delete','simple-pos');?></a></td></tr>
					<?php endforeach;?>
					</tbody>
				</table>
			</div>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="simple-pos-card simple-pos-filters">
				<?php wp_nonce_field('simple_pos_save_tax_class');?>
				<input type="hidden" name="action" value="simple_pos_save_tax_class" />
				<div class="simple-pos-filter-field simple-pos-filter-grow">
					<label><?php esc_html_e('Class name','simple-pos');?></label>
					<input type="text" name="name" placeholder="<?php esc_attr_e('e.g. Standard','simple-pos');?>" required />
				</div>
				<div class="simple-pos-filter-field simple-pos-filter-grow">
					<label><?php esc_html_e('Description','simple-pos');?></label>
					<input type="text" name="description" placeholder="<?php esc_attr_e('Description','simple-pos');?>" />
				</div>
				<div class="simple-pos-filter-actions">
					<button class="button button-primary" type="submit"><?php esc_html_e('Add Class','simple-pos');?></button>
				</div>
			</form>

			<div class="simple-pos-card simple-pos-table-card">
				<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e('Tax Rates','simple-pos');?></h2>
				<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px">
					<thead><tr><th><?php esc_html_e('Class','simple-pos');?></th><th><?php esc_html_e('Country','simple-pos');?></th><th><?php esc_html_e('State','simple-pos');?></th><th class="num"><?php esc_html_e('Rate %','simple-pos');?></th><th><?php esc_html_e('Name','simple-pos');?></th><th><?php esc_html_e('Inc','simple-pos');?></th><th><?php esc_html_e('Comp','simple-pos');?></th><th class="num"><?php esc_html_e('Prio','simple-pos');?></th><th><?php esc_html_e('Actions','simple-pos');?></th></tr></thead>
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
								<a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-taxes&edit_rate='.$r->id));?>"><?php esc_html_e('Edit','simple-pos');?></a>
								<a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_tax_rate&id='.$r->id),'simple_pos_delete_tax_rate'));?>" onclick="return confirm('<?php echo esc_js(__('Delete?','simple-pos'));?>')"><?php esc_html_e('Del','simple-pos');?></a>
							</td>
						</tr>
					<?php endforeach;?>
					<?php if(empty($rates)):?><tr><td colspan="9" class="simple-pos-empty"><?php esc_html_e('No rates. Seeded US/IN/EU defaults exist after activation — if empty, deactivate/reactivate or check DB.','simple-pos');?></td></tr><?php endif;?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php echo $edit_rate? esc_html__('Edit Rate','simple-pos'):esc_html__('Add Rate','simple-pos');?></span></h2><div class="inside">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
					<?php wp_nonce_field('simple_pos_save_tax_rate');?>
					<input type="hidden" name="action" value="simple_pos_save_tax_rate" />
					<input type="hidden" name="rate_id" value="<?php echo esc_attr($edit_rate->id ?? 0);?>" />
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Class','simple-pos');?> <span class="required" aria-hidden="true">*</span></label>
						<select name="class_id" class="widefat" required>
							<?php foreach($classes as $c): ?><option value="<?php echo esc_attr($c->id);?>" <?php selected($edit_rate->class_id ?? 0,$c->id);?>><?php echo esc_html($c->name);?></option><?php endforeach;?>
						</select>
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Country','simple-pos');?> <span class="required" aria-hidden="true">*</span> <span class="description"><?php esc_html_e('(ISO2 or * for all)','simple-pos');?></span></label>
						<input type="text" name="country_code" required value="<?php echo esc_attr($edit_rate->country_code ?? '');?>" class="widefat" placeholder="US, IN, DE, *" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('State','simple-pos');?> <span class="description"><?php esc_html_e('(optional)','simple-pos');?></span></label>
						<input type="text" name="state_code" value="<?php echo esc_attr($edit_rate->state_code ?? '');?>" class="widefat" placeholder="CA, MH" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Rate %','simple-pos');?> <span class="required" aria-hidden="true">*</span></label>
						<input type="number" step="0.0001" min="0" max="100" name="rate" required value="<?php echo esc_attr($edit_rate->rate ?? '');?>" class="widefat" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Name','simple-pos');?></label>
						<input type="text" name="name" value="<?php echo esc_attr($edit_rate->name ?? '');?>" class="widefat" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label><?php esc_html_e('Priority','simple-pos');?></label>
						<input type="number" name="priority" value="<?php echo esc_attr($edit_rate->priority ?? 0);?>" class="widefat" />
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label class="simple-pos-checkbox"><input type="checkbox" name="is_inclusive" value="1" <?php checked($edit_rate->is_inclusive ?? 0,1);?> /> <?php esc_html_e('Inclusive (price contains tax)','simple-pos');?></label>
					</div>
					<div class="simple-pos-form-row" style="margin-top:8px">
						<label class="simple-pos-checkbox"><input type="checkbox" name="is_compound" value="1" <?php checked($edit_rate->is_compound ?? 0,1);?> /> <?php esc_html_e('Compound (on top of prior taxes)','simple-pos');?></label>
					</div>
					<div class="simple-pos-form-actions" style="margin-top:10px">
						<button class="button button-primary" type="submit"><?php echo $edit_rate? esc_html__('Update','simple-pos'):esc_html__('Add','simple-pos');?> <?php esc_html_e('Rate','simple-pos');?></button>
						<?php if($edit_rate):?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-taxes'));?>"><?php esc_html_e('Cancel','simple-pos');?></a><?php endif;?>
					</div>
				</form>
			</div></div>
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php esc_html_e('Examples','simple-pos');?></span></h2><div class="inside"><p class="simple-pos-muted"><?php esc_html_e('US-CA 7.25% Standard | IN 18% GST | DE 19% VAT | FR reduced 5.5% | Zero * 0%','simple-pos');?><br><br><?php esc_html_e('Compound: e.g. Canada PST 7% compound on top of GST 5% — set GST prio 0, PST prio 1 compound=1.','simple-pos');?></p></div></div>
		</div>
	</div>
</div>
