<?php
// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a render method; locals are function-scoped, not globals.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$products = Simple_POS_Products::get_products( array( 'per_page' => 100, 'page' => 1, 'status' => 'active' ) );
$settings = Simple_POS_Settings::get_all();
?>
<div class="wrap simple-pos-wrap">
	<div class="simple-pos-page-header">
		<h1><?php esc_html_e('Barcode Labels','simple-pos');?></h1>
		<div class="simple-pos-page-actions">
			<button id="pos-print-labels" class="button button-primary"><?php esc_html_e('Print Labels','simple-pos');?></button>
		</div>
	</div>
	<p class="description"><?php esc_html_e('Select products/variants to print.','simple-pos');?> <?php esc_html_e('Symbology:','simple-pos');?> <strong><?php echo esc_html($settings['barcode_symbology']);?></strong> | <?php esc_html_e('Sheet:','simple-pos');?> <strong><?php echo esc_html($settings['barcode_label_format']);?></strong> <?php esc_html_e('(change in Settings)','simple-pos');?>.</p>
	<div class="simple-pos-columns">
		<div class="simple-pos-col-main">
			<div class="simple-pos-card simple-pos-filter-bar">
				<div class="simple-pos-filters">
					<div class="simple-pos-filter-actions">
						<label class="simple-pos-checkbox"><input type="checkbox" id="pos-select-all"/> <?php esc_html_e('Select all','simple-pos');?></label>
					</div>
				</div>
			</div>
			<div class="simple-pos-card simple-pos-table-card">
				<table class="wp-list-table widefat striped simple-pos-table">
					<thead><tr><th><input type="checkbox" disabled/></th><th><?php esc_html_e('Product / Variant','simple-pos');?></th><th><?php esc_html_e('SKU','simple-pos');?></th><th><?php esc_html_e('Barcode','simple-pos');?></th><th class="num"><?php esc_html_e('Price','simple-pos');?></th></tr></thead>
					<tbody>
					<?php if(empty($products['items'])):?><tr><td colspan="5" class="simple-pos-empty"><?php esc_html_e('No products.','simple-pos');?></td></tr><?php endif;?>
					<?php foreach($products['items'] as $p):
						$vars = Simple_POS_Variants::get_variants($p->id);
						$rows = array_merge(array(array('is_variant'=>0,'name'=>$p->name,'sku'=>$p->sku,'barcode'=>$p->barcode,'price'=>$p->price,'id'=>$p->id)), array_map(function($v) use($p){ return array('is_variant'=>1,'name'=>$p->name.' — '.Simple_POS_Variants::variant_label($v),'sku'=>$v->sku,'barcode'=>$v->barcode,'price'=>$v->price ?? $p->price,'id'=>$v->id,'parent'=>$p->id); }, $vars));
						foreach($rows as $r): if(empty($r['barcode']) && empty($r['sku'])) continue;
					?>
						<tr <?php echo $r['is_variant']?'class="is-variant"':'';?>>
							<td><input type="checkbox" class="pos-label-check" data-name="<?php echo esc_attr($r['name']);?>" data-barcode="<?php echo esc_attr($r['barcode']?:$r['sku']);?>" data-sku="<?php echo esc_attr($r['sku']);?>" data-price="<?php echo esc_attr(Simple_POS_DB::format_currency($r['price']));?>" /></td>
							<td><?php echo esc_html($r['name']);?></td>
							<td><code><?php echo esc_html($r['sku']);?></code></td>
							<td><?php echo esc_html($r['barcode']);?></td>
							<td class="num"><?php echo esc_html(Simple_POS_DB::format_currency($r['price']));?></td>
						</tr>
					<?php endforeach; endforeach;?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="simple-pos-col-side">
			<div class="postbox simple-pos-form-card"><h2 class="hndle"><span><?php esc_html_e('Preview','simple-pos');?></span></h2><div class="inside"><div id="pos-label-preview" class="pos-label-sheet" style="border:1px solid #ccc;padding:8px;min-height:120px"></div><p class="description"><?php esc_html_e('Uses JsBarcode (CODE128) in print view. For EAN13 ensure 13-digit numeric. QR fallback.','simple-pos');?></p></div></div>
		</div>
	</div>
	<!-- print container: vendor URL provided via wp_add_inline_script (window.SimplePOSVendorUrl); data attribute is a no-JS fallback. Logic lives in admin/js/barcode-labels.js (enqueued). -->
	<div id="pos-label-print" style="display:none" data-vendor-url="<?php echo esc_attr( SIMPLE_POS_PLUGIN_URL . 'admin/js/vendor/jsbarcode.min.js' ); ?>"></div>
</div>
