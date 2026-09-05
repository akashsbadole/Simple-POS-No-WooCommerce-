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
<div class="wrap simple-pos-wrap simple-pos-products-page">
	<div class="simple-pos-page-header">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Products', 'simple-pos' ); ?></h1>
		<div class="simple-pos-page-actions">
			<a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=simple-pos-barcode'));?>"><?php esc_html_e( 'Barcode Labels', 'simple-pos' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_export_products'), 'simple_pos_export_products'));?>"><?php esc_html_e( 'Export CSV', 'simple-pos' ); ?></a>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="simple-pos-inline-form">
				<?php wp_nonce_field('simple_pos_import_products');?><input type="hidden" name="action" value="simple_pos_import_products" />
				<input type="file" name="csv_file" accept=".csv" required />
				<button class="button" type="submit"><?php esc_html_e( 'Import CSV', 'simple-pos' ); ?></button>
			</form>
		</div>
	</div>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-filter-bar">
				<form method="get" class="simple-pos-filters">
					<input type="hidden" name="page" value="simple-pos-products" />
					<div class="simple-pos-filter-field simple-pos-filter-grow">
						<label for="filter-s"><?php esc_html_e( 'Search', 'simple-pos' ); ?></label>
						<input id="filter-s" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, SKU, barcode…', 'simple-pos' ); ?>" />
					</div>
					<div class="simple-pos-filter-field">
						<label for="filter-cat"><?php esc_html_e( 'Category', 'simple-pos' ); ?></label>
						<select id="filter-cat" name="category_id"><option value="0"><?php esc_html_e( 'All categories', 'simple-pos' ); ?></option><?php foreach ( $categories as $cat ) : ?><option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $category_id, $cat->id ); ?>><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?></select>
					</div>
					<div class="simple-pos-filter-field">
						<label for="filter-status"><?php esc_html_e( 'Status', 'simple-pos' ); ?></label>
						<select id="filter-status" name="status"><option value="any" <?php selected( $status, 'any' ); ?>><?php esc_html_e( 'Any status', 'simple-pos' ); ?></option><option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'simple-pos' ); ?></option><option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'simple-pos' ); ?></option></select>
					</div>
					<div class="simple-pos-filter-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'simple-pos' ); ?></button>
						<?php if ( $low_stock_filter ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products' ) ); ?>"><?php esc_html_e( 'Clear low-stock filter', 'simple-pos' ); ?></a><?php endif; ?>
					</div>
				</form>
			</div>
			<div class="simple-pos-card simple-pos-table-card">
				<table class="wp-list-table widefat striped simple-pos-products-table">
					<thead><tr><th><?php esc_html_e( 'Name', 'simple-pos' ); ?></th><th><?php esc_html_e( 'SKU', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Category', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Price', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Tax', 'simple-pos' ); ?></th><th class="num"><?php esc_html_e( 'Stock', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Status', 'simple-pos' ); ?></th><th><?php esc_html_e( 'Actions', 'simple-pos' ); ?></th></tr></thead>
					<tbody>
						<?php if ( empty( $products_result['items'] ) ) : ?><tr><td colspan="8" class="simple-pos-empty"><?php esc_html_e( 'No products found.', 'simple-pos' ); ?></td></tr>
						<?php else : foreach ( $products_result['items'] as $product ) :
							$cat_name='—'; foreach($categories as $cat) if((int)$cat->id===(int)$product->category_id){$cat_name=$cat->name;break;}
							$is_low=$product->track_stock && $product->stock_qty <= $product->low_stock_threshold;
							$tc_name='—'; foreach($tax_classes as $tc) if((int)$tc->id===(int)($product->tax_class_id??0)){$tc_name=$tc->name;break;}
							$is_variant=!empty($product->is_variant);
						?>
							<tr <?php echo $is_variant?'class="is-variant"':'';?>>
								<td><strong><?php echo esc_html( $product->name ); ?></strong> <?php if($is_variant) echo '<em class="simple-pos-pill">'.esc_html__('variant','simple-pos').'</em>';?></td>
								<td><code><?php echo esc_html( $product->sku ); ?></code></td><td><?php echo esc_html( $cat_name ); ?></td>
								<td class="num"><?php echo esc_html( Simple_POS_DB::format_currency( $product->price ) ); ?></td>
								<td><?php echo esc_html($tc_name);?> <?php if(!$is_variant && $product->tax_rate) echo '<small>('.esc_html($product->tax_rate).'%)</small>';?></td>
								<td class="num"><?php if ( $product->track_stock ) : ?><span class="<?php echo $is_low ? 'simple-pos-low-stock' : ''; ?>"><?php echo esc_html( $product->stock_qty ); ?></span><?php else : ?><em class="simple-pos-muted"><?php esc_html_e( 'not tracked', 'simple-pos' ); ?></em><?php endif; ?></td>
								<td><span class="simple-pos-status simple-pos-status-<?php echo esc_attr($product->status);?>"><?php echo esc_html( ucfirst( $product->status ) ); ?></span></td>
								<td class="simple-pos-row-actions">
									<?php if($is_variant):?><em class="simple-pos-muted"><?php esc_html_e('variant','simple-pos');?></em><?php else:?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products&edit=' . $product->id ) ); ?>"><?php esc_html_e( 'Edit', 'simple-pos' ); ?></a>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_product&id=' . $product->id ), 'simple_pos_delete_product' ) ); ?>" class="delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this product?', 'simple-pos' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-pos' ); ?></a>
									<?php endif;?>
								</td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $total_pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$paged,'total'=>$total_pages,) ) ); ?></div></div><?php endif; ?>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card">
				<h2 class="hndle"><span><?php echo $editing_product ? esc_html__( 'Edit Product', 'simple-pos' ) : esc_html__( 'Add Product', 'simple-pos' ); ?></span></h2>
				<div class="inside">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-product-form">
						<?php wp_nonce_field( 'simple_pos_save_product' ); ?><input type="hidden" name="action" value="simple_pos_save_product" /><input type="hidden" name="product_id" value="<?php echo esc_attr( $editing_product ? $editing_product->id : 0 ); ?>" />
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label><?php esc_html_e( 'Name', 'simple-pos' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input type="text" name="name" required value="<?php echo esc_attr( $editing_product->name ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'SKU', 'simple-pos' ); ?></label>
							<input type="text" name="sku" value="<?php echo esc_attr( $editing_product->sku ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Barcode', 'simple-pos' ); ?></label>
							<input type="text" name="barcode" value="<?php echo esc_attr( $editing_product->barcode ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label><?php esc_html_e( 'Category', 'simple-pos' ); ?></label>
							<select name="category_id" class="widefat"><option value=""><?php esc_html_e( 'None', 'simple-pos' ); ?></option><?php foreach ( $categories as $cat ) : ?><option value="<?php echo esc_attr( $cat->id ); ?>" <?php selected( $editing_product->category_id ?? 0, $cat->id ); ?>><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?></select>
						</div>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Price', 'simple-pos' ); ?> <span class="required" aria-hidden="true">*</span></label>
							<input type="number" step="0.01" min="0" name="price" required value="<?php echo esc_attr( $editing_product->price ?? '0.00' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Cost price', 'simple-pos' ); ?></label>
							<input type="number" step="0.01" min="0" name="cost_price" value="<?php echo esc_attr( $editing_product->cost_price ?? '0.00' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label><?php esc_html_e( 'Tax class', 'simple-pos' ); ?></label>
							<select name="tax_class_id" class="widefat">
								<?php foreach($tax_classes as $tc):?><option value="<?php echo esc_attr($tc->id);?>" <?php selected($editing_product->tax_class_id ?? Simple_POS_Settings::get('default_tax_class_id',0), $tc->id);?>><?php echo esc_html($tc->name);?></option><?php endforeach;?>
							</select>
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label><?php esc_html_e( 'Legacy tax rate (%)', 'simple-pos' ); ?></label>
							<input type="number" step="0.01" min="0" name="tax_rate" value="<?php echo esc_attr( $editing_product->tax_rate ?? Simple_POS_Settings::get( 'default_tax_rate', 0 ) ); ?>" class="widefat" />
							<p class="description"><?php esc_html_e( 'Fallback if no class rate matches', 'simple-pos' ); ?></p>
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label class="simple-pos-checkbox"><input type="checkbox" name="track_stock" value="1" <?php checked( $editing_product->track_stock ?? 1, 1 ); ?> /> <?php esc_html_e( 'Track stock for this product', 'simple-pos' ); ?></label>
						</div>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Stock quantity', 'simple-pos' ); ?></label>
							<input type="number" step="1" name="stock_qty" value="<?php echo esc_attr( $editing_product->stock_qty ?? 0 ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row">
							<label><?php esc_html_e( 'Low stock threshold', 'simple-pos' ); ?></label>
							<input type="number" step="1" min="0" name="low_stock_threshold" value="<?php echo esc_attr( $editing_product->low_stock_threshold ?? 5 ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label><?php esc_html_e( 'Image URL', 'simple-pos' ); ?></label>
							<input type="url" name="image_url" value="<?php echo esc_attr( $editing_product->image_url ?? '' ); ?>" class="widefat" />
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full">
							<label><?php esc_html_e( 'Status', 'simple-pos' ); ?></label>
							<select name="status" class="widefat"><option value="active" <?php selected( $editing_product->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'simple-pos' ); ?></option><option value="inactive" <?php selected( $editing_product->status ?? 'active', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'simple-pos' ); ?></option></select>
						</div>
						<div class="simple-pos-form-row simple-pos-form-row-full simple-pos-form-actions">
							<button type="submit" class="button button-primary"><?php echo $editing_product ? esc_html__( 'Update Product', 'simple-pos' ) : esc_html__( 'Add Product', 'simple-pos' ); ?></button>
							<?php if ( $editing_product ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-pos-products' ) ); ?>"><?php esc_html_e( 'Cancel', 'simple-pos' ); ?></a><?php endif; ?>
						</div>
					</form>
					<?php if ( $editing_product ) : ?><hr /><h4><?php esc_html_e( 'Quick restock', 'simple-pos' ); ?></h4>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-restock-form">
						<?php wp_nonce_field( 'simple_pos_adjust_stock' ); ?><input type="hidden" name="action" value="simple_pos_adjust_stock" /><input type="hidden" name="product_id" value="<?php echo esc_attr( $editing_product->id ); ?>" />
						<input type="number" name="delta" placeholder="<?php esc_attr_e( 'e.g. 10 or -2', 'simple-pos' ); ?>" required />
						<input type="text" name="note" placeholder="<?php esc_attr_e( 'Note (optional)', 'simple-pos' ); ?>" />
						<button type="submit" class="button"><?php esc_html_e( 'Apply', 'simple-pos' ); ?></button>
					</form><?php endif; ?>
				</div>
			</div>
			<?php if($editing_product): ?>
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php esc_html_e('Variants','simple-pos');?> — <?php esc_html_e('free-form attributes','simple-pos');?></span></h2><div class="inside">
				<?php if($variants_for_edit): ?><table class="widefat striped simple-pos-variants-table"><thead><tr><th><?php esc_html_e('Attributes','simple-pos');?></th><th><?php esc_html_e('SKU','simple-pos');?></th><th class="num"><?php esc_html_e('Price','simple-pos');?></th><th class="num"><?php esc_html_e('Stock','simple-pos');?></th><th></th></tr></thead><tbody>
					<?php foreach($variants_for_edit as $v): $lab=Simple_POS_Variants::variant_label($v);?>
						<tr><td><?php echo esc_html($lab);?></td><td><code><?php echo esc_html($v->sku);?></code></td><td class="num"><?php echo esc_html($v->price ?? '—');?></td><td class="num"><?php echo esc_html($v->stock_qty);?></td><td><a class="delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=simple_pos_delete_variant&id='.$v->id),'simple_pos_delete_variant'));?>" onclick="return confirm('<?php esc_attr_e('Delete variant?','simple-pos');?>')"><?php esc_html_e('Delete','simple-pos');?></a></td></tr>
					<?php endforeach;?></tbody></table><?php else: ?><p class="simple-pos-muted"><?php esc_html_e('No variants yet.','simple-pos');?></p><?php endif;?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="simple-pos-variant-form">
					<?php wp_nonce_field('simple_pos_save_variant');?><input type="hidden" name="action" value="simple_pos_save_variant" /><input type="hidden" name="parent_product_id" value="<?php echo esc_attr($editing_product->id);?>" />
					<h4><?php esc_html_e('Add variant','simple-pos');?></h4>
					<div id="pos-variant-attrs" class="simple-pos-attr-rows">
						<div class="simple-pos-attr-row"><input type="text" name="attr_key[]" placeholder="<?php esc_attr_e('Attr e.g. Size','simple-pos');?>"/><input type="text" name="attr_value[]" placeholder="<?php esc_attr_e('Value e.g. M','simple-pos');?>"/></div>
						<div class="simple-pos-attr-row"><input type="text" name="attr_key[]" placeholder="<?php esc_attr_e('Attr e.g. Color','simple-pos');?>"/><input type="text" name="attr_value[]" placeholder="<?php esc_attr_e('Value e.g. Red','simple-pos');?>"/></div>
					</div>
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('SKU','simple-pos');?></label>
						<input type="text" name="sku" class="widefat" />
					</div>
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Barcode','simple-pos');?></label>
						<input type="text" name="barcode" class="widefat" />
					</div>
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Price override','simple-pos');?></label>
						<input type="number" step="0.01" name="price" placeholder="<?php esc_attr_e('empty = parent','simple-pos');?>" class="widefat"/>
					</div>
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Cost','simple-pos');?></label>
						<input type="number" step="0.01" name="cost_price" class="widefat"/>
					</div>
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Stock','simple-pos');?></label>
						<input type="number" name="stock_qty" value="0" class="widefat"/>
					</div>
					<div class="simple-pos-form-row">
						<label><?php esc_html_e('Low thresh','simple-pos');?></label>
						<input type="number" name="low_stock_threshold" value="5" class="widefat"/>
					</div>
					<div class="simple-pos-form-row simple-pos-form-row-full">
						<label class="simple-pos-checkbox"><input type="checkbox" name="track_stock" value="1" checked /> <?php esc_html_e('Track stock','simple-pos');?></label>
					</div>
					<div class="simple-pos-form-row simple-pos-form-row-full">
						<label><?php esc_html_e('Image URL','simple-pos');?></label>
						<input type="url" name="image_url" class="widefat" />
					</div>
					<div class="simple-pos-form-row simple-pos-form-row-full simple-pos-form-actions">
						<button class="button button-primary" type="submit"><?php esc_html_e('Add Variant','simple-pos');?></button>
					</div>
				</form>
			</div></div>
			<?php endif;?>
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php esc_html_e( 'Categories', 'simple-pos' ); ?></span></h2><div class="inside">
				<ul class="simple-pos-category-list"><?php foreach ( $categories as $cat ) : ?><li><?php echo esc_html( $cat->name ); ?><a class="delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=simple_pos_delete_category&id=' . $cat->id ), 'simple_pos_delete_category' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this category? Products stay, just unassigned.', 'simple-pos' ) ); ?>');">&times;</a></li><?php endforeach; ?><?php if ( empty( $categories ) ) : ?><li class="simple-pos-muted"><?php esc_html_e( 'No categories yet.', 'simple-pos' ); ?></li><?php endif; ?></ul>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="simple-pos-cat-form">
					<?php wp_nonce_field( 'simple_pos_save_category' ); ?><input type="hidden" name="action" value="simple_pos_save_category" />
					<input type="text" name="name" placeholder="<?php esc_attr_e( 'New category name', 'simple-pos' ); ?>" required class="widefat" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Category', 'simple-pos' ); ?></button>
				</form>
			</div></div>
		</div>
	</div>
</div>
