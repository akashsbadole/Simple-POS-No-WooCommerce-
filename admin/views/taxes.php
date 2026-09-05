<?php if ( ! defined( 'ABSPATH' ) ) exit;
$classes = Simple_POS_Tax::get_classes();
$rates = Simple_POS_Tax::get_rates();
$edit_rate_id = isset($_GET['edit_rate']) ? (int)$_GET['edit_rate'] : 0;
$edit_rate = $edit_rate_id ? Simple_POS_Tax::get_rate($edit_rate_id) : null;
?>
<div class="wrap simple-pos-wrap">
	<h1><?php esc_html_e('Taxes — per-country classes & rates','simple-pos');?></h1>
	<p class="description"><?php esc_html_e('Set IS country codes (US, IN, DE, FR, GB...) + optional state (CA, MH). Priority low runs first; compound applies on top of prior taxes. Inclusive = price already contains tax.','simple-pos');?></p>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<h2><?php esc_html_e('Tax Classes','simple-pos');?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Action</th></tr></thead>
				<tbody>
				<?php foreach($classes as $c): ?>
					<tr><td><strong><?php echo esc_html($c->name);?></strong></td><td><?php echo esc_html($c->slug);?></td><td><?php echo esc_html($c->description);?></td>
					<td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_tax_class&id='.$c->id),'simple_pos_delete_tax_class'));?>" onclick="return confirm('Delete tax class?')">Delete</a></td></tr>
				<?php endforeach;?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="margin-top:12px">
				<?php wp_nonce_field('simple_pos_save_tax_class');?>
				<input type="hidden" name="action" value="simple_pos_save_tax_class" />
				<input type="text" name="name" placeholder="Class name e.g. Standard" required />
				<input type="text" name="description" placeholder="Description" />
				<button class="button" type="submit">Add Class</button>
			</form>

			<h2 style="margin-top:24px"><?php esc_html_e('Tax Rates','simple-pos');?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Class</th><th>Country</th><th>State</th><th>Rate %</th><th>Name</th><th>Inc</th><th>Comp</th><th>Prio</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach($rates as $r): ?>
					<tr>
						<td><?php echo esc_html($r->class_slug ?? $r->class_id);?></td>
						<td><?php echo esc_html($r->country_code);?></td>
						<td><?php echo esc_html($r->state_code ?: '—');?></td>
						<td><?php echo esc_html($r->rate);?>%</td>
						<td><?php echo esc_html($r->name);?></td>
						<td><?php echo $r->is_inclusive?'Yes':'No';?></td>
						<td><?php echo $r->is_compound?'Yes':'No';?></td>
						<td><?php echo esc_html($r->priority);?></td>
						<td><a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-taxes&edit_rate='.$r->id));?>">Edit</a> | <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_tax_rate&id='.$r->id),'simple_pos_delete_tax_rate'));?>" onclick="return confirm('Delete?')">Del</a></td>
					</tr>
				<?php endforeach;?>
				<?php if(empty($rates)):?><tr><td colspan="9">No rates. Seeded US/IN/EU defaults exist after activation — if empty, deactivate/reactivate or check DB.</td></tr><?php endif;?>
				</tbody>
			</table>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox"><h2 class="hndle"><span><?php echo $edit_rate? 'Edit Rate':'Add Rate';?></span></h2><div class="inside">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
					<?php wp_nonce_field('simple_pos_save_tax_rate');?>
					<input type="hidden" name="action" value="simple_pos_save_tax_rate" />
					<input type="hidden" name="rate_id" value="<?php echo esc_attr($edit_rate->id ?? 0);?>" />
					<p><label>Class *</label><select name="class_id" class="widefat" required>
						<?php foreach($classes as $c): ?><option value="<?php echo esc_attr($c->id);?>" <?php selected($edit_rate->class_id ?? 0,$c->id);?>><?php echo esc_html($c->name);?></option><?php endforeach;?>
					</select></p>
					<p><label>Country * (ISO2 or * for all)</label><input type="text" name="country_code" required value="<?php echo esc_attr($edit_rate->country_code ?? '');?>" class="widefat" placeholder="US, IN, DE, *" /></p>
					<p><label>State (optional)</label><input type="text" name="state_code" value="<?php echo esc_attr($edit_rate->state_code ?? '');?>" class="widefat" placeholder="CA, MH" /></p>
					<p><label>Rate % *</label><input type="number" step="0.0001" min="0" max="100" name="rate" required value="<?php echo esc_attr($edit_rate->rate ?? '');?>" class="widefat" /></p>
					<p><label>Name</label><input type="text" name="name" value="<?php echo esc_attr($edit_rate->name ?? '');?>" class="widefat" /></p>
					<p><label>Priority</label><input type="number" name="priority" value="<?php echo esc_attr($edit_rate->priority ?? 0);?>" class="widefat" /></p>
					<p><label><input type="checkbox" name="is_inclusive" value="1" <?php checked($edit_rate->is_inclusive ?? 0,1);?> /> Inclusive (price contains tax)</label></p>
					<p><label><input type="checkbox" name="is_compound" value="1" <?php checked($edit_rate->is_compound ?? 0,1);?> /> Compound (on top of prior taxes)</label></p>
					<p><button class="button button-primary" type="submit"><?php echo $edit_rate? 'Update':'Add';?> Rate</button> <?php if($edit_rate):?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-taxes'));?>">Cancel</a><?php endif;?></p>
				</form>
			</div></div>
			<div class="postbox"><div class="inside"><p><strong>Examples:</strong><br>US-CA 7.25% Standard | IN 18% GST | DE 19% VAT | FR reduced 5.5% | Zero * 0%<br><br>Compound: e.g. Canada PST 7% compound on top of GST 5% — set GST prio 0, PST prio 1 compound=1.</p></div></div>
		</div>
	</div>
</div>
