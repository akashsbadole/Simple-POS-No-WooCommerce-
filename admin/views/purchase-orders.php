<?php if(!defined('ABSPATH')) exit;
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
$view_id = isset($_GET['view'])? (int)$_GET['view']:0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if($view_id){
	$po=Simple_POS_Purchase_Orders::get_order($view_id);
	if(!$po){ echo '<div class="wrap"><p>PO not found.</p></div>'; return; }
	$items=Simple_POS_Purchase_Orders::get_items($po->id);
	$supplier=$po->supplier_id? Simple_POS_Suppliers::get_supplier($po->supplier_id):null;
	?>
	<div class="wrap simple-pos-wrap">
		<div class="simple-pos-page-header">
			<?php /* translators: %s: purchase order number. */ ?>
			<h1><?php echo esc_html(sprintf(__('PO %s','simple-pos'),$po->po_number));?></h1>
			<div class="simple-pos-page-actions">
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-purchase-orders'));?>"><?php esc_html_e('Back to POs','simple-pos');?></a>
			</div>
		</div>
		<span class="simple-pos-status simple-pos-status-<?php echo esc_attr($po->status);?>"><?php echo esc_html(ucfirst($po->status));?></span>

		<div class="simple-pos-card simple-pos-table-card" style="margin-top:12px">
			<table class="widefat striped simple-pos-table"><tbody>
				<tr><th><?php esc_html_e('Status','simple-pos');?></th><td><?php echo esc_html(ucfirst($po->status));?></td></tr>
				<tr><th><?php esc_html_e('Supplier','simple-pos');?></th><td><?php echo esc_html($supplier->name ?? '—');?></td></tr>
				<tr><th><?php esc_html_e('Total cost','simple-pos');?></th><td><?php echo esc_html(Simple_POS_DB::format_currency($po->total_cost));?></td></tr>
				<tr><th><?php esc_html_e('Note','simple-pos');?></th><td><?php echo esc_html($po->note);?></td></tr>
				<tr><th><?php esc_html_e('Created','simple-pos');?></th><td><?php echo esc_html($po->created_at);?></td></tr>
			</tbody></table>
		</div>

		<div class="simple-pos-card simple-pos-table-card" style="margin-top:12px">
			<h2 class="simple-pos-section-title" style="margin:0;padding:14px 14px 0"><?php esc_html_e('Items','simple-pos');?></h2>
			<table class="wp-list-table widefat striped simple-pos-table" style="margin-top:10px"><thead><tr><th><?php esc_html_e('Product','simple-pos');?></th><th><?php esc_html_e('Variant','simple-pos');?></th><th class="num"><?php esc_html_e('Ordered','simple-pos');?></th><th class="num"><?php esc_html_e('Received','simple-pos');?></th><th class="num"><?php esc_html_e('Cost','simple-pos');?></th></tr></thead><tbody>
			<?php foreach($items as $it): $p=$it->product_id? Simple_POS_Products::get_product($it->product_id):null; $v=$it->variant_id? Simple_POS_Variants::get_variant($it->variant_id):null;?>
				<tr><td><?php echo esc_html($p->name ?? '—');?></td><td><?php echo esc_html($v? Simple_POS_Variants::variant_label($v):'—');?></td><td class="num"><?php echo esc_html($it->qty);?></td><td class="num"><?php echo esc_html($it->received_qty);?></td><td class="num"><?php echo esc_html(Simple_POS_DB::format_currency($it->cost_price));?></td></tr>
			<?php endforeach;?>
			</tbody></table>
		</div>

		<?php if(!in_array($po->status,array('received','cancelled'),true)):?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="simple-pos-card"><?php wp_nonce_field('simple_pos_receive_po');?><input type="hidden" name="action" value="simple_pos_receive_po"/><input type="hidden" name="po_id" value="<?php echo esc_attr($po->id);?>"/>
			<h3 style="margin:0 0 6px"><?php esc_html_e('Receive','simple-pos');?></h3>
			<p class="simple-pos-muted"><?php esc_html_e('Enter quantity received for each item (leave 0 to skip).','simple-pos');?></p>
			<?php foreach($items as $it): $rem=$it->qty - $it->received_qty; if($rem<=0) continue; $p=$it->product_id? Simple_POS_Products::get_product($it->product_id):null;?>
				<div class="simple-pos-form-row" style="margin-top:6px">
					<?php /* translators: %d: quantity remaining to receive. */ ?>
				<label><?php echo esc_html(($p->name ?? __('Item','simple-pos')).' — '.sprintf(__('remaining %d','simple-pos'),$rem)); ?></label>
					<input type="number" name="receive_qty[<?php echo esc_attr($it->id);?>]" min="0" max="<?php echo esc_attr($rem);?>" value="<?php echo esc_attr($rem);?>" />
				</div>
			<?php endforeach;?>
			<div class="simple-pos-form-actions" style="margin-top:10px">
				<button class="button button-primary" type="submit"><?php esc_html_e('Receive & Update Stock','simple-pos');?></button>
			</div>
		</form>
		<?php endif;?>
	</div>
	<?php return; }
$orders=Simple_POS_Purchase_Orders::get_orders(array('per_page'=>20,'page'=> isset($_GET['paged'])? (int)$_GET['paged']:1)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$suppliers=Simple_POS_Suppliers::get_suppliers();
$all_products=Simple_POS_Products::get_products(array('per_page'=>Simple_POS_DB::MAX_PER_PAGE,'page'=>1,'status'=>'active'));
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e('Purchase Orders','simple-pos');?></h1>
	</div>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-table-card">
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr><th><?php esc_html_e('PO #','simple-pos');?></th><th><?php esc_html_e('Supplier','simple-pos');?></th><th><?php esc_html_e('Status','simple-pos');?></th><th class="num"><?php esc_html_e('Total','simple-pos');?></th><th><?php esc_html_e('Date','simple-pos');?></th><th><?php esc_html_e('Actions','simple-pos');?></th></tr></thead>
					<tbody>
					<?php if(empty($orders['items'])):?><tr><td colspan="6" class="simple-pos-empty"><?php esc_html_e('No POs yet.','simple-pos');?></td></tr><?php else: foreach($orders['items'] as $po): $sup=$po->supplier_id? Simple_POS_Suppliers::get_supplier($po->supplier_id):null;?>
					<tr>
						<td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-purchase-orders&view='.$po->id));?>"><?php echo esc_html($po->po_number);?></a></strong></td>
						<td><?php echo esc_html($sup->name ?? '—');?></td>
						<td><span class="simple-pos-status simple-pos-status-<?php echo esc_attr($po->status);?>"><?php echo esc_html(ucfirst($po->status));?></span></td>
						<td class="num"><?php echo esc_html(Simple_POS_DB::format_currency($po->total_cost));?></td>
						<td><?php echo esc_html($po->created_at);?></td>
						<td class="simple-pos-row-actions">
							<a href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-purchase-orders&view='.$po->id));?>"><?php esc_html_e('View','simple-pos');?></a>
							<a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_po&id='.$po->id),'simple_pos_delete_po'));?>" onclick="return confirm('<?php echo esc_js(__('Delete PO?','simple-pos'));?>')"><?php esc_html_e('Del','simple-pos');?></a>
						</td>
					</tr>
					<?php endforeach; endif;?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php esc_html_e('New Purchase Order','simple-pos');?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('simple_pos_save_po');?><input type="hidden" name="action" value="simple_pos_save_po"/>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e('Supplier','simple-pos');?></label>
							<select name="supplier_id" class="widefat"><option value="">— <?php esc_html_e('None','simple-pos');?> —</option><?php foreach($suppliers as $s):?><option value="<?php echo esc_attr($s->id);?>"><?php echo esc_html($s->name);?></option><?php endforeach;?></select>
						</div>
						<div class="simple-pos-form-row" style="margin-top:8px">
							<label><?php esc_html_e('Note','simple-pos');?></label>
							<textarea name="note" class="widefat" rows="2"></textarea>
						</div>
						<div id="po-items" style="margin-top:8px">
							<div class="po-item" style="border:1px solid #eee;padding:8px;margin-bottom:6px;border-radius:4px">
								<div class="simple-pos-form-row">
									<label><?php esc_html_e('Product','simple-pos');?></label>
									<select name="po_product_id[]" class="widefat po-product-select"><option value=""><?php esc_html_e('Select product','simple-pos');?></option><?php foreach($all_products['items'] as $p):?><option value="<?php echo esc_attr($p->id);?>"><?php echo esc_html($p->name.' ('.$p->sku.')');?></option><?php endforeach;?></select>
								</div>
								<div class="simple-pos-form-row" style="margin-top:6px">
									<label><?php esc_html_e('Variant ID','simple-pos');?> <span class="description"><?php esc_html_e('(optional, for variant restock)','simple-pos');?></span></label>
									<input type="number" name="po_variant_id[]" class="widefat" placeholder="variant id" />
								</div>
								<div style="display:flex;gap:8px;margin-top:6px">
									<span style="flex:1" class="simple-pos-form-row"><label><?php esc_html_e('Qty','simple-pos');?></label><input type="number" name="po_qty[]" value="10" min="1" class="widefat"/></span>
									<span style="flex:1" class="simple-pos-form-row"><label><?php esc_html_e('Cost/unit','simple-pos');?></label><input type="number" step="0.01" name="po_cost[]" value="0" class="widefat"/></span>
								</div>
							</div>
						</div>
						<button type="button" class="button" onclick="var c=document.querySelector('.po-item').cloneNode(true); c.querySelectorAll('input').forEach(i=>i.value=''); document.getElementById('po-items').appendChild(c);" style="margin-top:4px">+ <?php esc_html_e('Add line','simple-pos');?></button>
						<div class="simple-pos-form-actions" style="margin-top:10px">
							<button class="button button-primary" type="submit"><?php esc_html_e('Create PO','simple-pos');?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
