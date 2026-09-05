<?php if(!defined('ABSPATH')) exit;
$products = Simple_POS_Products::get_products(array('per_page'=>100,'page'=>1,'status'=>'active'));
$settings = Simple_POS_Settings::get_all();
?>
<div class="wrap simple-pos-wrap"><h1>Barcode Labels</h1>
<p>Select products/variants to print. Symbology: <?php echo esc_html($settings['barcode_symbology']);?> | Sheet: <?php echo esc_html($settings['barcode_label_format']);?> (change in Settings).</p>
<div class="simple-pos-columns">
<div class="simple-pos-col-main">
	<div style="margin-bottom:12px"><label><input type="checkbox" id="pos-select-all"/> Select all</label> <button id="pos-print-labels" class="button button-primary">Print Labels</button></div>
	<table class="wp-list-table widefat fixed striped"><thead><tr><th><input type="checkbox" disabled/></th><th>Product / Variant</th><th>SKU</th><th>Barcode</th><th>Price</th></tr></thead><tbody>
	<?php foreach($products['items'] as $p):
		$vars = Simple_POS_Variants::get_variants($p->id);
		$rows = array_merge(array(array('is_variant'=>0,'name'=>$p->name,'sku'=>$p->sku,'barcode'=>$p->barcode,'price'=>$p->price,'id'=>$p->id)), array_map(function($v) use($p){ return array('is_variant'=>1,'name'=>$p->name.' — '.Simple_POS_Variants::variant_label($v),'sku'=>$v->sku,'barcode'=>$v->barcode,'price'=>$v->price ?? $p->price,'id'=>$v->id,'parent'=>$p->id); }, $vars));
		foreach($rows as $r): if(empty($r['barcode']) && empty($r['sku'])) continue;
	?>
		<tr><td><input type="checkbox" class="pos-label-check" data-name="<?php echo esc_attr($r['name']);?>" data-barcode="<?php echo esc_attr($r['barcode']?:$r['sku']);?>" data-price="<?php echo esc_attr(Simple_POS_DB::format_currency($r['price']));?>" /></td>
			<td><?php echo esc_html($r['name']);?></td><td><?php echo esc_html($r['sku']);?></td><td><?php echo esc_html($r['barcode']);?></td><td><?php echo esc_html(Simple_POS_DB::format_currency($r['price']));?></td></tr>
	<?php endforeach; endforeach;?>
	</tbody></table>
</div>
<div class="simple-pos-col-side">
	<div class="postbox"><h2 class="hndle"><span>Preview</span></h2><div class="inside"><div id="pos-label-preview" class="pos-label-sheet" style="border:1px solid #ccc;padding:8px;min-height:120px"></div><p class="description">Uses JsBarcode (CODE128) in print view. For EAN13 ensure 13-digit numeric. QR fallback.</p></div></div>
</div>
</div>
<!-- print container -->
<div id="pos-label-print" style="display:none"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
	var checks=document.querySelectorAll('.pos-label-check');
	var selAll=document.getElementById('pos-select-all');
	var btn=document.getElementById('pos-print-labels');
	if(selAll) selAll.addEventListener('change',function(){ checks.forEach(function(c){ c.checked=selAll.checked; }); renderPreview(); });
	checks.forEach(function(c){ c.addEventListener('change',renderPreview); });
	function renderPreview(){
		var container=document.getElementById('pos-label-preview'); if(!container) return; container.innerHTML='';
		var selected=[]; checks.forEach(function(c){ if(c.checked) selected.push(c); });
		if(!selected.length){ container.innerHTML='<em>No labels selected</em>'; return; }
		selected.forEach(function(c){
			var div=document.createElement('div'); div.className='pos-label'; div.style.cssText='border:1px dashed #999; padding:8px; margin:6px; text-align:center; display:inline-block; width:180px';
			var name=document.createElement('div'); name.textContent=c.dataset.name; name.style.fontSize='11px'; name.style.fontWeight='600';
			var svg=document.createElementNS('http://www.w3.org/2000/svg','svg'); svg.style.width='100%'; svg.style.height='50px';
			var price=document.createElement('div'); price.textContent=c.dataset.price; price.style.fontSize='10px';
			div.appendChild(name); div.appendChild(svg); div.appendChild(price); container.appendChild(div);
			try{
				var code=c.dataset.barcode||'000000';
				if(window.JsBarcode) JsBarcode(svg, code, {format: "CODE128", displayValue:true, fontSize:10, height:40});
			}catch(e){}
		});
	}
	btn.addEventListener('click',function(){
		var sel=[]; checks.forEach(function(c){ if(c.checked) sel.push({name:c.dataset.name, code:c.dataset.barcode||c.dataset.sku, price:c.dataset.price}); });
		if(!sel.length) return alert('Select at least one');
		var win=window.open('','_blank');
		var html='<!doctype html><html><head><title>Labels</title><style>'+
			'@media print{ @page{ size:A4; margin:10mm } } body{font-family:sans-serif} .sheet{display:flex;flex-wrap:wrap;gap:6px} .label{border:1px solid #000; width:62mm; height:32mm; padding:4mm; text-align:center; box-sizing:border-box; display:flex; flex-direction:column; justify-content:center} .label svg{width:100%;height:18mm} .label .name{font-size:9px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis} .label .price{font-size:10px}</style>'+
			'<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script></head><body><div class="sheet">';
		sel.forEach(function(s,i){
			// repeat each 2 copies for sheet demo? single
			html+='<div class="label"><div class="name">'+s.name.replace(/</g,'&lt;')+'</div><svg id="bc'+i+'"></svg><div class="price">'+s.price+'</div></div>';
		});
		html+='</div><script>window.onload=function(){';
		sel.forEach(function(s,i){ html+='try{JsBarcode(document.getElementById("bc'+i+'"),"'+s.code.replace(/"/g,'\\"')+'",{format:"CODE128",displayValue:true,fontSize:9,height:36});}catch(e){}'; });
		html+=' setTimeout(function(){window.print();},400);} <\/script></body></html>';
		win.document.write(html); win.document.close();
	});
});
</script>
<style>.pos-label-sheet .pos-label{page-break-inside:avoid}</style>
