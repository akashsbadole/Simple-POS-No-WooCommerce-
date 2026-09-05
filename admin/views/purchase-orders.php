<?php if(!defined('ABSPATH')) exit;
$view_id = isset($_GET['view'])? (int)$_GET['view']:0;
if($view_id){
	$po=Simple_POS_Purchase_Orders::get_order($view_id);
	if(!$po){ echo '<div class="wrap"><p>PO not found.</p></div>'; return; }
	$items=Simple_POS_Purchase_Orders::get_items($po->id);
	$supplier=$po->supplier_id? Simple_POS_Suppliers::get_supplier($po->supplier_id):null;
	?>
	<div class="wrap simple-pos-wrap"><h1>PO <?php echo esc_html($po->po_number);?></h1><p><a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-purchase-orders'));?>">&larr; Back</a></p>
	<table class="widefat striped"><tbody>
		<tr><th>Status</th><td><?php echo esc_html(ucfirst($po->status));?></td></tr>
		<tr><th>Supplier</th><td><?php echo esc_html($supplier->name ?? '—');?></td></tr>
		<tr><th>Total cost</th><td><?php echo esc_html(Simple_POS_DB::format_currency($po->total_cost));?></td></tr>
		<tr><th>Note</th><td><?php echo esc_html($po->note);?></td></tr>
		<tr><th>Created</th><td><?php echo esc_html($po->created_at);?></td></tr>
	</tbody></table>
	<h2>Items</h2>
	<table class="wp-list-table widefat fixed striped"><thead><tr><th>Product</th><th>Variant</th><th>Ordered</th><th>Received</th><th>Cost</th></tr></thead><tbody>
	<?php foreach($items as $it): $p=$it->product_id? Simple_POS_Products::get_product($it->product_id):null; $v=$it->variant_id? Simple_POS_Variants::get_variant($it->variant_id):null;?>
		<tr><td><?php echo esc_html($p->name ?? '—');?></td><td><?php echo esc_html($v? Simple_POS_Variants::variant_label($v):'—');?></td><td><?php echo esc_html($it->qty);?></td><td><?php echo esc_html($it->received_qty);?></td><td><?php echo esc_html(Simple_POS_DB::format_currency($it->cost_price));?></td></tr>
	<?php endforeach;?>
	</tbody></table>
	<?php if(!in_array($po->status,array('received','cancelled'),true)):?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="margin-top:16px"><?php wp_nonce_field('simple_pos_receive_po');?><input type="hidden" name="action" value="simple_pos_receive_po"/><input type="hidden" name="po_id" value="<?php echo esc_attr($po->id);?>"/>
	<h3>Receive</h3><p>Enter quantity received for each item (leave 0 to skip):</p>
	<?php foreach($items as $it): $rem=$it->qty - $it->received_qty; if($rem<=0) continue; $p=$it->product_id? Simple_POS_Products::get_product($it->product_id):null;?>
		<p><label><?php echo esc_html(($p->name ?? 'Item').' (remaining '.$rem.')'); ?> <input type="number" name="receive_qty[<?php echo esc_attr($it->id);?>]" min="0" max="<?php echo esc_attr($rem);?>" value="<?php echo esc_attr($rem);?>" /></label></p>
	<?php endforeach;?>
	<button class="button button-primary" type="submit">Receive & Update Stock</button>
	</form>
	<?php endif;?>
	</div>
	<?php return; }
$orders=Simple_POS_Purchase_Orders::get_orders(array('per_page'=>20,'page'=> isset($_GET['paged'])? (int)$_GET['paged']:1));
$suppliers=Simple_POS_Suppliers::get_suppliers();
$all_products=Simple_POS_Products::get_products(array('per_page'=>200,'page'=>1,'status'=>'active'));
?>
<div class="wrap simple-pos-wrap"><h1>Purchase Orders</h1>
<div class="simple-pos-columns">
<div class="simple-pos-col-main">
<table class="wp-list-table widefat fixed striped"><thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Total</th><th>Date</th><th>Actions</th></tr></thead><tbody>
<?php if(empty($orders['items'])):?><tr><td colspan="6">No POs yet.</td></tr><?php else: foreach($orders['items'] as $po): $sup=$po->supplier_id? Simple_POS_Suppliers::get_supplier($po->supplier_id):null;?>
<tr><td><a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-purchase-orders&view='.$po->id));?>"><?php echo esc_html($po->po_number);?></a></td><td><?php echo esc_html($sup->name ?? '—');?></td><td><?php echo esc_html(ucfirst($po->status));?></td><td><?php echo esc_html(Simple_POS_DB::format_currency($po->total_cost));?></td><td><?php echo esc_html($po->created_at);?></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-purchase-orders&view='.$po->id));?>">View</a> | <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_po&id='.$po->id),'simple_pos_delete_po'));?>" onclick="return confirm('Delete PO?')">Del</a></td></tr>
<?php endforeach; endif;?>
</tbody></table>
</div>
<div class="simple-pos-col-side">
<div class="postbox"><h2 class="hndle"><span>New Purchase Order</span></h2><div class="inside">
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('simple_pos_save_po');?><input type="hidden" name="action" value="simple_pos_save_po"/>
<p><label>Supplier</label><select name="supplier_id" class="widefat"><option value="">— None —</option><?php foreach($suppliers as $s):?><option value="<?php echo esc_attr($s->id);?>"><?php echo esc_html($s->name);?></option><?php endforeach;?></select></p>
<p><label>Note</label><textarea name="note" class="widefat" rows="2"></textarea></p>
<div id="po-items">
	<div class="po-item" style="border:1px solid #eee;padding:8px;margin-bottom:6px">
		<p><label>Product</label><select name="po_product_id[]" class="widefat po-product-select"><option value="">Select product</option><?php foreach($all_products['items'] as $p):?><option value="<?php echo esc_attr($p->id);?>"><?php echo esc_html($p->name.' ('.$p->sku.')');?></option><?php endforeach;?></select></p>
		<p><label>Variant ID (optional, for variant restock)</label><input type="number" name="po_variant_id[]" class="widefat" placeholder="variant id" /></p>
		<p style="display:flex;gap:8px"><span style="flex:1"><label>Qty</label><input type="number" name="po_qty[]" value="10" min="1" class="widefat"/></span><span style="flex:1"><label>Cost/unit</label><input type="number" step="0.01" name="po_cost[]" value="0" class="widefat"/></span></p>
	</div>
</div>
<button type="button" class="button" onclick="var c=document.querySelector('.po-item').cloneNode(true); c.querySelectorAll('input').forEach(i=>i.value=''); document.getElementById('po-items').appendChild(c);">+ Add line</button>
<p><button class="button button-primary" type="submit">Create PO</button></p>
</form>
</div></div>
</div>
</div>
</div>
