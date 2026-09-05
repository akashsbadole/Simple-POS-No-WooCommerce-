<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$editing_id      = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
$editing_product = $editing_id ? Simple_POS_Products::get_product( $editing_id ) : null;
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
$status      = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'any';
$low_stock_filter = isset( $_GET['filter'] ) && 'low_stock' === $_GET['filter'];
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$categories = Simple_POS_Products::get_categories();
$tax_classes = class_exists('Simple_POS_Tax')? Simple_POS_Tax::get_classes(): array();
if ( $low_stock_filter ) {
	$products_result = array( 'items' => Simple_POS_Products::get_low_stock_products( 100 ), 'total' => 0 );
	$products_result['total'] = count( $products_result['items'] );
} else {
	$products_result = Simple_POS_Products::get_products( array(
		'search'      => $search,
		'category_id' => $category_id,
		'status'      => $status,
		'per_page'    => 20,
		'page'        => $paged,
	) );
}
$total_pages = $low_stock_filter ? 1 : (int) ceil( $products_result['total'] / 20 );
$variants_for_edit = $editing_product ? Simple_POS_Variants::get_variants($editing_product->id,'any') : array();
?>
<div class="wrap simple-pos-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Products', 'simple-pos' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_export_products'), 'simple_pos_export_products'));?>">Export CSV</a>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="display:inline-block;margin-left:8px">
		<?php wp_nonce_field('simple_pos_import_products');?><input type="hidden" name="action" value="simple_pos_import_products" />
		<input type="file" name="csv_file" accept=".csv" required style="display:inline;width:180px" />
		<button class="button" type="submit">Import CSV</button>
	</form>
	<a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-barcode'));?>">Barcode Labels</a>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<form method="get" class="simple-pos-filters">
				<input type="hidden" name="page" value="simple-pos-products" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, SKU, barcode…', 'simple-pos' ); ?>" />
				<select name="category_id"><option value="0"><?php esc_html_e( 'All categories', 'simple-pos' ); ?></option><?php foreach ( $categories as $cat ) : ?><option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $category_id, $cat->id ); ?>><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?></select>
				<select name="status"><option value="any" <?php selected( $status, 'any' ); ?>><?php esc_html_e( 'Any status', 'simple-pos' ); ?></option><option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'simple-pos' ); ?></option><option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'simple-pos' ); ?></option></select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'simple-pos' ); ?></button>
				<?php if ( $low_stock_filter ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products' ) ); ?>"><?php esc_html_e( 'Clear low-stock filter', 'simple-pos' ); ?></a><?php endif; ?>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Name', 'simple-pos' ); ?></th><th><?php esc_html_e( 'SKU', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Category', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Price', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Tax Class', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Stock', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $products_result['items'] ) ) : ?><tr><td colspan="8"><?php esc_html_e( 'No products found.', 'simple-pos' ); ?></td></tr>
					<?php else : foreach ( $products_result['items'] as $product ) : 
						$cat_name='—'; foreach($categories as $cat) if((int)$cat->id===(int)$product->category_id){$cat_name=$cat->name;break;}
						$is_low=$product->track_stock && $product->stock_qty <= $product->low_stock_threshold;
						$tc_name='—'; foreach($tax_classes as $tc) if((int)$tc->id===(int)($product->tax_class_id??0)){$tc_name=$tc->name;break;}
						$is_variant=!empty($product->is_variant);
					?>
							<tr <?php echo $is_variant?'style="background:#f6f7f7"':'';?>>
								<td><strong><?php echo esc_html( $product->name ); ?></strong> <?php if($is_variant) echo '<em>(variant)</em>';?></td>
								<td><?php echo esc_html( $product->sku ); ?></td><td><?php echo esc_html( $cat_name ); ?></td>
								<td><?php echo esc_html( Simple_POS_DB::format_currency( $product->price ) ); ?></td>
								<td><?php echo esc_html($tc_name);?> <?php if(!$is_variant && $product->tax_rate) echo '('.esc_html($product->tax_rate).'%)';?></td>
								<td><?php if ( $product->track_stock ) : ?><span class="<?php echo $is_low ? 'simple-pos-low-stock' : ''; ?>"><?php echo esc_html( $product->stock_qty ); ?></span><?php else : ?><em><?php esc_html_e( 'not tracked', 'simple-pos' ); ?></em><?php endif; ?></td>
								<td><?php echo esc_html( ucfirst( $product->status ) ); ?></td>
								<td>
									<?php if($is_variant):?><em>variant</em><?php else:?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products&edit=' . $product->id ) ); ?>"><?php esc_html_e( 'Edit', 'simple-pos' ); ?></a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_product&id=' . $product->id ), 'simple_pos_delete_product' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this product?', 'simple-pos' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-pos' ); ?></a>
									<?php endif;?>
								</td>
							</tr>
						<?php endforeach; endif; ?>
				</tbody>
			</table>
			<?php if ( $total_pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$paged,'total'=>$total_pages,) ) ); ?></div></div><?php endif; ?>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox">
				<h2 class="hndle"><span><?php echo $editing_product ? esc_html__( 'Edit Product', 'simple-pos' ) : esc_html__( 'Add Product', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'simple_pos_save_product' ); ?><input type="hidden" name="action" value="simple_pos_save_product" /><input type="hidden" name="product_id" value="<?php echo esc_attr( $editing_product ? $editing_product->id : 0 ); ?>" />
						<p><label><?php esc_html_e( 'Name', 'simple-pos' ); ?> *</label><input type="text" name="name" required value="<?php echo esc_attr( $editing_product->name ?? '' ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'SKU', 'simple-pos' ); ?></label><input type="text" name="sku" value="<?php echo esc_attr( $editing_product->sku ?? '' ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Barcode', 'simple-pos' ); ?></label><input type="text" name="barcode" value="<?php echo esc_attr( $editing_product->barcode ?? '' ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Category', 'simple-pos' ); ?></label><select name="category_id" class="widefat"><option value=""><?php esc_html_e( 'None', 'simple-pos' ); ?></option><?php foreach ( $categories as $cat ) : ?><option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $editing_product->category_id ?? 0, $cat->id ); ?>><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?></select></p>
						<p><label><?php esc_html_e( 'Price', 'simple-pos' ); ?> *</label><input type="number" step="0.01" min="0" name="price" required value="<?php echo esc_attr( $editing_product->price ?? '0.00' ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Cost price', 'simple-pos' ); ?></label><input type="number" step="0.01" min="0" name="cost_price" value="<?php echo esc_attr( $editing_product->cost_price ?? '0.00' ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Tax class', 'simple-pos' ); ?></label><select name="tax_class_id" class="widefat">
							<?php foreach($tax_classes as $tc):?><option value="<?php echo esc_attr($tc->id);?>" <?php selected($editing_product->tax_class_id ?? Simple_POS_Settings::get('default_tax_class_id',0), $tc->id);?>><?php echo esc_html($tc->name);?></option><?php endforeach;?>
						</select></p>
						<p><label><?php esc_html_e( 'Legacy tax rate (%)', 'simple-pos' ); ?></label><input type="number" step="0.01" min="0" name="tax_rate" value="<?php echo esc_attr( $editing_product->tax_rate ?? Simple_POS_Settings::get( 'default_tax_rate', 0 ) ); ?>" class="widefat" /><span class="description">Fallback if no class rate matches</span></p>
						<p><label><input type="checkbox" name="track_stock" value="1" <?php checked( $editing_product->track_stock ?? 1, 1 ); ?> /> <?php esc_html_e( 'Track stock for this product', 'simple-pos' ); ?></label></p>
						<p><label><?php esc_html_e( 'Stock quantity', 'simple-pos' ); ?></label><input type="number" step="1" name="stock_qty" value="<?php echo esc_attr( $editing_product->stock_qty ?? 0 ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Low stock threshold', 'simple-pos' ); ?></label><input type="number" step="1" min="0" name="low_stock_threshold" value="<?php echo esc_attr( $editing_product->low_stock_threshold ?? 5 ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Image URL', 'simple-pos' ); ?></label><input type="url" name="image_url" value="<?php echo esc_attr( $editing_product->image_url ?? '' ); ?>" class="widefat" /></p>
						<p><label><?php esc_html_e( 'Status', 'simple-pos' ); ?></label><select name="status" class="widefat"><option value="active" <?php selected( $editing_product->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'simple-pos' ); ?></option><option value="inactive" <?php selected( $editing_product->status ?? 'active', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'simple-pos' ); ?></option></select></p>
						<p><button type="submit" class="button button-primary"><?php echo $editing_product ? esc_html__( 'Update Product', 'simple-pos' ) : esc_html__( 'Add Product', 'simple-pos' ); ?></button><?php if ( $editing_product ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products' ) ); ?>"><?php esc_html_e( 'Cancel', 'simple-pos' ); ?></a><?php endif; ?></p>
					</form>
					<?php if ( $editing_product ) : ?><hr /><h4><?php esc_html_e( 'Quick restock', 'simple-pos' ); ?></h4><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'simple_pos_adjust_stock' ); ?><input type="hidden" name="action" value="simple_pos_adjust_stock" /><input type="hidden" name="product_id" value="<?php echo esc_attr( $editing_product->id ); ?>" /><p><input type="number" name="delta" placeholder="<?php esc_attr_e( 'e.g. 10 or -2', 'simple-pos' ); ?>" required /><input type="text" name="note" placeholder="<?php esc_attr_e( 'Note (optional)', 'simple-pos' ); ?>" /><button type="submit" class="button"><?php esc_html_e( 'Apply', 'simple-pos' ); ?></button></p></form><?php endif; ?>
				</div>
			</div>
			<?php if($editing_product): ?>
			<div class="postbox"><h2 class="hndle"><span>Variants — free-form attributes</span></h2><div class="inside">
				<?php if($variants_for_edit): ?><table class="widefat striped"><thead><tr><th>Attributes</th><th>SKU</th><th>Price</th><th>Stock</th><th></th></tr></thead><tbody>
					<?php foreach($variants_for_edit as $v): $lab=Simple_POS_Variants::variant_label($v);?>
						<tr><td><?php echo esc_html($lab);?></td><td><?php echo esc_html($v->sku);?></td><td><?php echo esc_html($v->price ?? '—');?></td><td><?php echo esc_html($v->stock_qty);?></td><td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_variant&id='.$v->id),'simple_pos_delete_variant'));?>" onclick="return confirm('Delete variant?')">Del</a></td></tr>
					<?php endforeach;?></tbody></table><?php else: ?><p><em>No variants yet.</em></p><?php endif;?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
					<?php wp_nonce_field('simple_pos_save_variant');?><input type="hidden" name="action" value="simple_pos_save_variant" /><input type="hidden" name="parent_product_id" value="<?php echo esc_attr($editing_product->id);?>" />
					<p><strong>Add variant</strong></p>
					<div id="pos-variant-attrs">
						<div style="display:flex;gap:6px"><input type="text" name="attr_key[]" placeholder="Attr e.g. Size" style="flex:1"/><input type="text" name="attr_value[]" placeholder="Value e.g. M" style="flex:1"/></div>
						<div style="display:flex;gap:6px;margin-top:4px"><input type="text" name="attr_key[]" placeholder="Attr e.g. Color" style="flex:1"/><input type="text" name="attr_value[]" placeholder="Value e.g. Red" style="flex:1"/></div>
					</div>
					<p><label>SKU</label><input type="text" name="sku" class="widefat" /></p>
					<p><label>Barcode</label><input type="text" name="barcode" class="widefat" /></p>
					<p style="display:flex;gap:8px"><span style="flex:1"><label>Price override</label><input type="number" step="0.01" name="price" placeholder="empty = parent" class="widefat"/></span><span style="flex:1"><label>Cost</label><input type="number" step="0.01" name="cost_price" class="widefat"/></span></p>
					<p style="display:flex;gap:8px"><span style="flex:1"><label>Stock</label><input type="number" name="stock_qty" value="0" class="widefat"/></span><span style="flex:1"><label>Low thresh</label><input type="number" name="low_stock_threshold" value="5" class="widefat"/></span></p>
					<p><label><input type="checkbox" name="track_stock" value="1" checked /> Track stock</label></p>
					<p><label>Image URL</label><input type="url" name="image_url" class="widefat" /></p>
					<p><button class="button button-primary" type="submit">Add Variant</button></p>
				</form>
			</div></div>
			<?php endif;?>
			<div class="postbox"><h2 class="hndle"><span><?php esc_html_e( 'Categories', 'simple-pos' ); ?></span></h2><div class="inside"><ul class="simple-pos-category-list"><?php foreach ( $categories as $cat ) : ?><li><?php echo esc_html( $cat->name ); ?><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_category&id=' . $cat->id ), 'simple_pos_delete_category' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this category? Products stay, just unassigned.', 'simple-pos' ) ); ?>');">&times;</a></li><?php endforeach; ?><?php if ( empty( $categories ) ) : ?><li><em><?php esc_html_e( 'No categories yet.', 'simple-pos' ); ?></em></li><?php endif; ?></ul><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'simple_pos_save_category' ); ?><input type="hidden" name="action" value="simple_pos_save_category" /><p><input type="text" name="name" placeholder="<?php esc_attr_e( 'New category name', 'simple-pos' ); ?>" required class="widefat" /></p><button type="submit" class="button"><?php esc_html_e( 'Add Category', 'simple-pos' ); ?></button></form></div></div>
		</div>
	</div>
</div>
