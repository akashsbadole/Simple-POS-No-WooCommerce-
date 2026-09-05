<?php if(!defined('ABSPATH')) exit;
$editing_id = isset($_GET['edit'])? (int)$_GET['edit']:0;
$editing = $editing_id? Simple_POS_Suppliers::get_supplier($editing_id):null;
$suppliers = Simple_POS_Suppliers::get_suppliers();
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e('Suppliers','simple-pos');?></h1>
	</div>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr><th><?php esc_html_e('Name','simple-pos');?></th><th><?php esc_html_e('Contact','simple-pos');?></th><th><?php esc_html_e('Phone','simple-pos');?></th><th><?php esc_html_e('Email','simple-pos');?></th><th><?php esc_html_e('Actions','simple-pos');?></th></tr></thead>
					<tbody>
					<?php if(empty($suppliers)):?><tr><td colspan="5" class="simple-pos-empty"><?php esc_html_e('No suppliers.','simple-pos');?></td></tr><?php else: foreach($suppliers as $s):?>
						<tr>
							<td><strong><?php echo esc_html($s->name);?></strong></td>
							<td><?php echo esc_html($s->contact_name);?></td>
							<td><?php echo esc_html($s->phone);?></td>
							<td><?php echo esc_html($s->email);?></td>
							<td class="simple-pos-row-actions">
								<a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-suppliers&edit='.$s->id));?>"><?php esc_html_e('Edit','simple-pos');?></a>
								<a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_supplier&id='.$s->id),'simple_pos_delete_supplier'));?>" onclick="return confirm('<?php echo esc_js(__('Delete?','simple-pos'));?>')"><?php esc_html_e('Delete','simple-pos');?></a>
							</td>
						</tr>
					<?php endforeach; endif;?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php echo $editing? esc_html__('Edit Supplier','simple-pos'):esc_html__('Add Supplier','simple-pos');?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('simple_pos_save_supplier');?><input type="hidden" name="action" value="simple_pos_save_supplier"/><input type="hidden" name="supplier_id" value="<?php echo esc_attr($editing->id??0);?>"/>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e('Name','simple-pos');?> <span class="required" aria-hidden="true">*</span></label>
							<input type="text" name="name" required value="<?php echo esc_attr($editing->name??'');?>" class="widefat"/>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e('Contact name','simple-pos');?></label>
							<input type="text" name="contact_name" value="<?php echo esc_attr($editing->contact_name??'');?>" class="widefat"/>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e('Phone','simple-pos');?></label>
							<input type="text" name="phone" value="<?php echo esc_attr($editing->phone??'');?>" class="widefat"/>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e('Email','simple-pos');?></label>
							<input type="email" name="email" value="<?php echo esc_attr($editing->email??'');?>" class="widefat"/>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e('Address','simple-pos');?></label>
							<textarea name="address" class="widefat" rows="3"><?php echo esc_textarea($editing->address??'');?></textarea>
						</div>
						<div class="simple-pos-form-actions" style="margin-top:10px">
							<button class="button button-primary" type="submit"><?php echo $editing? esc_html__('Update','simple-pos'):esc_html__('Add','simple-pos');?></button>
							<?php if($editing):?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-suppliers'));?>"><?php esc_html_e('Cancel','simple-pos');?></a><?php endif;?>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
