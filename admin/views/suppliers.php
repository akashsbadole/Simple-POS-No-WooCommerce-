<?php if(!defined('ABSPATH')) exit;
$editing_id = isset($_GET['edit'])? (int)$_GET['edit']:0;
$editing = $editing_id? Simple_POS_Suppliers::get_supplier($editing_id):null;
$suppliers = Simple_POS_Suppliers::get_suppliers();
?>
<div class="wrap simple-pos-wrap"><h1>Suppliers</h1>
<div class="simple-pos-columns">
<div class="simple-pos-col-main">
<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Actions</th></tr></thead><tbody>
<?php if(empty($suppliers)):?><tr><td colspan="5">No suppliers.</td></tr><?php else: foreach($suppliers as $s):?><tr>
<td><strong><?php echo esc_html($s->name);?></strong></td><td><?php echo esc_html($s->contact_name);?></td><td><?php echo esc_html($s->phone);?></td><td><?php echo esc_html($s->email);?></td>
<td><a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-suppliers&edit='.$s->id));?>">Edit</a> | <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_supplier&id='.$s->id),'simple_pos_delete_supplier'));?>" onclick="return confirm('Delete?')">Delete</a></td>
</tr><?php endforeach; endif;?>
</tbody></table>
</div>
<div class="simple-pos-col-side">
<div class="postbox"><h2 class="hndle"><span><?php echo $editing? 'Edit Supplier':'Add Supplier';?></span></h2><div class="inside">
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('simple_pos_save_supplier');?><input type="hidden" name="action" value="simple_pos_save_supplier"/><input type="hidden" name="supplier_id" value="<?php echo esc_attr($editing->id??0);?>"/>
<p><label>Name *</label><input type="text" name="name" required value="<?php echo esc_attr($editing->name??'');?>" class="widefat"/></p>
<p><label>Contact name</label><input type="text" name="contact_name" value="<?php echo esc_attr($editing->contact_name??'');?>" class="widefat"/></p>
<p><label>Phone</label><input type="text" name="phone" value="<?php echo esc_attr($editing->phone??'');?>" class="widefat"/></p>
<p><label>Email</label><input type="email" name="email" value="<?php echo esc_attr($editing->email??'');?>" class="widefat"/></p>
<p><label>Address</label><textarea name="address" class="widefat" rows="3"><?php echo esc_textarea($editing->address??'');?></textarea></p>
<p><button class="button button-primary" type="submit"><?php echo $editing? 'Update':'Add';?></button> <?php if($editing):?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-suppliers'));?>">Cancel</a><?php endif;?></p>
</form>
</div></div>
</div>
</div>
</div>
