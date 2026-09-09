/**
 * Simple POS — Terminal screen logic.
 * Uses tax classes + variants + USB ESC/POS.
 */
(function(){
'use strict';
if(typeof window.SimplePOS==='undefined'){
	var root=document.getElementById('simple-pos-terminal');
	if(root){
		root.innerHTML='<div class="notice notice-error" style="margin:1em"><p>POS Terminal failed to initialize. Please reload the page.</p></div>';
	}
	return;
}
var state = {
	products: [], productsTotal:0, page:1, perPage:24, categories:[], activeCategory:0, search:'', cart:[], customers:[],
	taxRatesCache: {}, heldCarts: [], lastSaleId: null
};
var els = {};
function apiFetch(path,options){
	options=options||{}; options.headers=Object.assign({'X-WP-Nonce':window.SimplePOS.nonce,'Content-Type':'application/json'},options.headers||{});
	return fetch(window.SimplePOS.restUrl+path, options).then(function(response){ return response.json().then(function(body){ if(!response.ok){ var m=(body&&body.message)?body.message:'Request failed'; return Promise.reject(new Error(m)); } return body; }); });
}
function formatCurrency(amount){
	var num=Number(amount||0).toFixed(window.SimplePOS.currency.decimals);
	return 'after'===window.SimplePOS.currency.position? num+window.SimplePOS.currency.symbol : window.SimplePOS.currency.symbol+num;
}
function debounce(fn,wait){ var t; return function(){ var a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(null,a); },wait); }; }
function safeLoad(label, fn, fallback){
	return fn().catch(function(err){
		try{ console.warn('[SimplePOS] '+label+' failed:', err); }catch(e){}
		if(els.cartError){
			els.cartError.textContent = (err && err.message) ? err.message : ('Failed to load '+label+'.');
		}
		if(typeof fallback === 'function'){ fallback(); }
		return null;
	});
}
function bindNewCustomer(){
	if(!els.newCustomerBtn || !els.newCustomerModal) return;
	var caps = (window.SimplePOS && window.SimplePOS.caps) || {};
	if(caps.manageCustomers){
		els.newCustomerBtn.hidden = false;
	}
	function focusable(){
		return Array.prototype.slice.call(
			els.newCustomerModal.querySelectorAll('input, button, [tabindex]:not([tabindex="-1"])')
		).filter(function(el){ return !el.disabled && el.offsetParent !== null; });
	}
	function trapTab(e){
		if(e.key !== 'Tab') return;
		var items = focusable();
		if(!items.length) return;
		var first = items[0], last = items[items.length - 1];
		var active = document.activeElement;
		if(e.shiftKey && active === first){ e.preventDefault(); last.focus(); }
		else if(!e.shiftKey && active === last){ e.preventDefault(); first.focus(); }
	}
	function open(){
		els.newCustomerName.value = '';
		els.newCustomerPhone.value = '';
		els.newCustomerEmail.value = '';
		els.newCustomerError.textContent = '';
		els.newCustomerSave.disabled = false;
		els.newCustomerModal.hidden = false;
		setTimeout(function(){
			els.newCustomerName.focus();
			els.newCustomerName.select();
		}, 0);
	}
	function close(returnFocus){
		els.newCustomerModal.hidden = true;
		if(returnFocus && els.newCustomerBtn) els.newCustomerBtn.focus();
		else if(els.scanInput) els.scanInput.focus();
	}
	els.newCustomerBtn.addEventListener('click', open);
	if(els.newCustomerCancel) els.newCustomerCancel.addEventListener('click', function(){ close(true); });
	if(els.newCustomerModal){
		els.newCustomerModal.addEventListener('click', function(e){
			if(e.target === els.newCustomerModal) close(true);
		});
		els.newCustomerModal.addEventListener('keydown', trapTab);
	}
	var form = document.getElementById('simple-pos-new-customer-form');
	if(form){
		form.addEventListener('submit', function(e){
			e.preventDefault();
			if(els.newCustomerSave.disabled) return;
			var name = (els.newCustomerName.value || '').trim();
			if(!name){
				els.newCustomerError.textContent = 'Name is required.';
				els.newCustomerName.focus();
				return;
			}
			var phone = (els.newCustomerPhone.value || '').trim();
			var email = (els.newCustomerEmail.value || '').trim();
			els.newCustomerError.textContent = '';
			els.newCustomerSave.disabled = true;
			apiFetch('/customers', {
				method: 'POST',
				body: JSON.stringify({ name: name, phone: phone, email: email })
			}).then(function(res){
				var id = res && (res.id || (res.customer && res.customer.id));
				if(!id) throw new Error('Customer was created but no ID was returned.');
				state.customers.push({ id: id, name: name, phone: phone, email: email });
				renderCustomerSelect();
				els.customerSelect.value = String(id);
				close(false);
			}).catch(function(err){
				els.newCustomerError.textContent = (err && err.message) ? err.message : 'Could not create customer.';
				els.newCustomerName.focus();
			}).then(function(){
				els.newCustomerSave.disabled = false;
			});
		});
	}
}
function bindAddToCart(){
	// Add-to-cart reliability: surface any REST error from /products/{id} or /products/lookup/{code}
	// via els.cartError instead of silently no-op'ing.
	els.productGrid.addEventListener('click', function(e){
		var card = e.target.closest('.simple-pos-product-card');
		if(!card || card.disabled) return;
		var id = parseInt(card.dataset.id, 10);
		var product = state.products.find(function(p){ return p.id === id; });
		if(!product){ return; }
		apiFetch('/products/'+id).then(function(full){
			addProductToCart(coerceProduct(full));
		}).catch(function(){
			els.cartError.textContent = 'Could not load product details. Added with cached info only.';
			addProductToCart(coerceProduct(product));
		});
	});
}
function loadCategories(){ return apiFetch('/categories').then(function(d){ state.categories=d||[]; renderCategoryTabs(); }); }
function loadProducts(){
	var params=new URLSearchParams({per_page:state.perPage, page:state.page, status:'active'});
	if(state.search) params.set('search',state.search);
	if(state.activeCategory) params.set('category_id',state.activeCategory);
	return apiFetch('/products?'+params.toString()).then(function(data){
		state.products=coerceProducts(data.items||[]); state.productsTotal=data.total||0; renderProductGrid(); renderPagination();
	});
}
function loadCustomers(){ return apiFetch('/customers?per_page=100').then(function(d){ state.customers=(d&&d.items)||[]; renderCustomerSelect(); }); }
function renderCategoryTabs(){
	var html='<button type="button" class="simple-pos-cat-tab'+(0===state.activeCategory?' active':'')+'" data-cat="0">All</button>';
	state.categories.forEach(function(cat){ html+='<button type="button" class="simple-pos-cat-tab'+(state.activeCategory===cat.id?' active':'')+'" data-cat="'+cat.id+'">'+escapeHtml(cat.name)+'</button>'; });
	els.categoryTabs.innerHTML=html;
}
function renderProductGrid(){
	if(!state.products.length){ els.productGrid.innerHTML='<p class="simple-pos-loading-msg">No products found.</p>'; return; }
	var html='';
	state.products.forEach(function(p){
		var outOfStock=p.track_stock && Number(p.stock_qty)<=0;
		var hasVariants = p.variants && p.variants.length;
		html+='<button type="button" class="simple-pos-product-card'+(outOfStock?' out-of-stock':'')+'" data-id="'+p.id+'"'+(outOfStock?' disabled':'')+'>'+
			'<span class="simple-pos-product-name">'+escapeHtml(p.name)+(hasVariants?' <small>('+p.variants.length+' variants)</small>':'')+'</span>'+
			'<span class="simple-pos-product-price">'+formatCurrency(p.price)+'</span>'+
			(p.track_stock?'<span class="simple-pos-product-stock">'+(outOfStock?window.SimplePOS.i18n.outOfStock:p.stock_qty+' in stock')+'</span>':'')+
		'</button>';
	});
	els.productGrid.innerHTML=html;
}
function renderPagination(){
	var totalPages=Math.ceil(state.productsTotal/state.perPage);
	if(totalPages<=1){ els.pagination.innerHTML=''; return; }
	var html=''; for(var i=1;i<=totalPages;i++){ html+='<button type="button" class="simple-pos-page-btn'+(i===state.page?' active':'')+'" data-page="'+i+'">'+i+'</button>'; }
	els.pagination.innerHTML=html;
}
function renderCustomerSelect(){
	var html='<option value="">Walk-in customer</option>';
	state.customers.forEach(function(c){ html+='<option value="'+c.id+'">'+escapeHtml(c.name)+(c.phone?' ('+escapeHtml(c.phone)+')':'')+'</option>'; });
	els.customerSelect.innerHTML=html;
}
function renderCart(){
	if(!state.cart.length){
		els.cartItems.innerHTML='<p class="simple-pos-cart-empty">'+window.SimplePOS.i18n.cartEmpty+'</p>';
		els.checkoutBtn.disabled=true; renderTotals(); updateHoldRecallButtons(); return;
	}
	var html='';
	state.cart.forEach(function(item,index){
		var label=item.name;
		if(item.variant_label) label+=' <small>('+escapeHtml(item.variant_label)+')</small>';
		html+='<div class="simple-pos-cart-item" data-index="'+index+'">'+
			'<div class="simple-pos-cart-item-info"><span class="simple-pos-cart-item-name">'+label+'</span><span class="simple-pos-cart-item-price">'+formatCurrency(item.price)+' each</span></div>'+
			'<div class="simple-pos-cart-item-qty"><button type="button" class="simple-pos-qty-btn" data-action="dec" data-index="'+index+'">−</button><span>'+item.qty+'</span><button type="button" class="simple-pos-qty-btn" data-action="inc" data-index="'+index+'">+</button></div>'+
			'<div class="simple-pos-cart-item-total">'+formatCurrency(item.price*item.qty)+'</div>'+
			'<button type="button" class="simple-pos-cart-item-remove" data-index="'+index+'" aria-label="Remove">&times;</button></div>';
	});
	els.cartItems.innerHTML=html;
	els.checkoutBtn.disabled=false;
	renderTotals();
	updateHoldRecallButtons();
}
// Try server-side tax calc for accuracy, fallback to simple.
var totalsCache=null;
function renderTotals(){
	var subtotal=0;
	state.cart.forEach(function(item){ subtotal+=item.price*item.qty; });
	var discountInput=parseFloat(els.discountValue.value)||0;
	var discountType=els.discountType.value;
	var discount='percent'===discountType? subtotal*(discountInput/100): Math.min(discountInput,subtotal);
	// Attempt async tax calc for breakdown; immediate estimate shown first
	var estTax=0; // fallback
	state.cart.forEach(function(item){
		// no class info client-side yet, fallback 0
		estTax+=0;
	});
	els.subtotalEl.textContent=formatCurrency(subtotal);
	els.discountAmountEl.textContent=formatCurrency(discount);
	// If we have cached breakdown use it else show dash and fetch
	if(totalsCache && totalsCache.subtotal===subtotal){
		els.taxEl.textContent=formatCurrency(totalsCache.tax);
		els.totalEl.textContent=formatCurrency(totalsCache.total);
		els.taxBreakdownEl.innerHTML=(totalsCache.breakdown||[]).map(function(b){ return escapeHtml(b.name)+' '+b.rate+'% '+formatCurrency(b.amount); }).join('<br>');
		var paid=parseFloat(els.amountPaid.value)||0;
		var change=Math.max(0,paid - totalsCache.total);
		els.changeDueEl.textContent=formatCurrency(change);
		if(!els.amountPaid.dataset.touched){ els.amountPaid.value=totalsCache.total.toFixed(window.SimplePOS.currency.decimals); }
	} else {
		els.taxEl.textContent='calculating…';
		els.taxBreakdownEl.textContent='';
		// fetch preview
		var payload={
			lines: state.cart.map(function(it){ return {product_id:it.product_id, qty:it.qty, price:it.price}; }),
			country: (els.taxCountry&&els.taxCountry.value)||window.SimplePOS.tax.country||'US',
			state: (els.taxState&&els.taxState.value)||window.SimplePOS.tax.state||'',
			discount_type: discountType,
			discount_amount: discountInput
		};
		apiFetch('/tax/calculate',{method:'POST', body: JSON.stringify(payload)}).then(function(res){
			totalsCache={subtotal:res.subtotal, tax:res.tax, total:res.total, breakdown:res.breakdown};
			renderTotals();
		}).catch(function(){
			totalsCache=null;
			var total=Math.max(0,subtotal - discount);
			els.taxEl.textContent=formatCurrency(0);
			els.taxBreakdownEl.textContent='';
			els.totalEl.textContent=formatCurrency(total);
			if(!els.amountPaid.dataset.touched){ els.amountPaid.value=total.toFixed(window.SimplePOS.currency.decimals); }
			var paid2=parseFloat(els.amountPaid.value)||0;
			els.changeDueEl.textContent=formatCurrency(Math.max(0,paid2-total));
		});
		// immediate total without tax
		var totalFallback=Math.max(0,subtotal - discount);
		els.totalEl.textContent=formatCurrency(totalFallback);
		if(!els.amountPaid.dataset.touched){ els.amountPaid.value=totalFallback.toFixed(window.SimplePOS.currency.decimals); }
	}
	if(state.cart.length && totalsCache){
		var paid0=parseFloat(els.amountPaid.value)||0;
		els.changeDueEl.textContent=formatCurrency(Math.max(0,paid0 - totalsCache.total));
	}
}
function escapeHtml(str){ var div=document.createElement('div'); div.textContent=String(str==null?'':str); return div.innerHTML; }
function coerceTrackStock(val){ return Number(val) === 1; }
function coerceProduct(p){
	if(!p) return p;
	p.track_stock = coerceTrackStock(p.track_stock);
	if(p.id != null) p.id = parseInt(p.id, 10);
	return p;
}
function coerceProducts(arr){ return (arr||[]).map(coerceProduct); }
function addProductToCart(product){
	product = coerceProduct(product);
	if(product.track_stock && Number(product.stock_qty)<=0) return;
	// If product has variants, show picker instead of adding directly
	if(product.variants && product.variants.length){
		showVariantPicker(product);
		return;
	}
	var existing=state.cart.find(function(i){ return i.product_id===product.id && !i.variant_id; });
	if(existing){ existing.qty+=1; } else {
		state.cart.push({product_id:product.id, variant_id:null, name:product.name, sku:product.sku, price:parseFloat(product.price), qty:1, stock_qty:product.stock_qty, track_stock:!!product.track_stock});
	}
	els.amountPaid.dataset.touched=''; totalsCache=null; renderCart();
}
function showVariantPicker(product){
	product = coerceProduct(product);
	var modal=document.getElementById('simple-pos-variant-modal');
	var container=document.getElementById('simple-pos-variant-options');
	container.innerHTML='';
	product.variants.forEach(function(v){
		v = coerceProduct(v);
		if(v.status!=='active') return;
		var label='';
		try{ var attrs=JSON.parse(v.attributes||'{}'); label=Object.keys(attrs).map(function(k){return k+': '+attrs[k];}).join(' / ')||'Variant #'+v.id; } catch(e){ label='Variant #'+v.id; }
		var btn=document.createElement('button'); btn.type='button'; btn.className='button'; btn.style.display='block'; btn.style.width='100%'; btn.style.marginBottom='6px';
		var price=v.price!=null? parseFloat(v.price): parseFloat(product.price);
		btn.textContent=label+' — '+formatCurrency(price)+' ('+v.stock_qty+' in stock)';
		if(v.track_stock && Number(v.stock_qty)<=0) btn.disabled=true;
		btn.addEventListener('click',function(){
			var existing=state.cart.find(function(i){ return i.variant_id===v.id; });
			if(existing){ existing.qty+=1; } else {
				state.cart.push({product_id:product.id, variant_id:v.id, name:product.name, variant_label:label, sku:v.sku||product.sku, price:price, qty:1, stock_qty:v.stock_qty, track_stock:!!v.track_stock});
			}
			closeVariantPicker();
		});
		container.appendChild(btn);
	});
	modal.hidden=false;
	modal.setAttribute('role', 'dialog');
	modal.setAttribute('aria-label', 'Select product variant');
	modal.setAttribute('aria-modal', 'true');
	
	// Focus first button
	var firstBtn = container.querySelector('button:not([disabled])');
	if (firstBtn) firstBtn.focus();
	
	// Trap focus and handle escape key
	modal.addEventListener('keydown', function trapFocus(e) {
		if (e.key === 'Tab') {
			var focusables = modal.querySelectorAll('button:not([disabled])');
			var first = focusables[0];
			var last = focusables[focusables.length - 1];
			
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		} else if (e.key === 'Escape') {
			closeVariantPicker();
		}
	});
	
	document.getElementById('simple-pos-variant-cancel').onclick=function(){ closeVariantPicker(); };
}

function closeVariantPicker() {
	var modal = document.getElementById('simple-pos-variant-modal');
	modal.hidden = true;
	modal.removeAttribute('role');
	modal.removeAttribute('aria-label');
	modal.removeAttribute('aria-modal');
	
	// Return focus to scan input
	els.scanInput.focus();
}
function changeQty(index,delta){
	var item=state.cart[index]; if(!item) return; item.qty+=delta; if(item.qty<=0) state.cart.splice(index,1);
	els.amountPaid.dataset.touched=''; totalsCache=null; renderCart();
}
function removeItem(index){ state.cart.splice(index,1); els.amountPaid.dataset.touched=''; totalsCache=null; renderCart(); }
function clearCart(){ state.cart=[]; totalsCache=null; els.discountValue.value=0; els.amountPaid.value=''; els.amountPaid.dataset.touched=''; els.cartError.textContent=''; els.taxBreakdownEl.innerHTML=''; renderCart(); }
function holdCart(){
	if(!state.cart.length) return;
	state.heldCarts.push({
		cart: JSON.parse(JSON.stringify(state.cart)),
		customer_id: els.customerSelect.value||0,
		customer_type: els.customerType?els.customerType.value:'b2c',
		discount_type: els.discountType.value,
		discount_amount: els.discountValue.value
	});
	try{ localStorage.setItem('simple_pos_held_carts', JSON.stringify(state.heldCarts)); }catch(e){}
	clearCart();
	updateHoldRecallButtons();
}
function recallCart(){
	if(!state.heldCarts.length) return;
	var held=state.heldCarts.pop();
	state.cart = held.cart;
	els.customerSelect.value = String(held.customer_id||'');
	if(els.customerType) els.customerType.value = held.customer_type||'b2c';
	els.discountType.value = held.discount_type||'fixed';
	els.discountValue.value = held.discount_amount||0;
	try{ localStorage.setItem('simple_pos_held_carts', JSON.stringify(state.heldCarts)); }catch(e){}
	els.amountPaid.dataset.touched=''; totalsCache=null; renderCart();
	updateHoldRecallButtons();
}
function updateHoldRecallButtons(){
	if(els.holdBtn) els.holdBtn.disabled = !state.cart.length;
	if(els.recallBtn) els.recallBtn.disabled = !state.heldCarts.length;
}
function voidLastSale(saleId){
	if(!saleId){ els.cartError.textContent='No recent sale to void.'; return; }
	if(!confirm(window.SimplePOS.i18n.confirmVoid||'Void this sale?')) return;
	els.cartError.textContent = 'Voiding sale #'+saleId+'…';
	apiFetch('/sales/'+saleId+'/void',{method:'POST', body: '{}'}).then(function(){
		els.cartError.textContent = 'Sale #'+saleId+' voided. Stock restored.';
		state.lastSaleId = null;
		if(els.voidLastBtn) els.voidLastBtn.disabled = true;
		if(els.voidReceiptBtn) els.voidReceiptBtn.disabled = true;
		loadProducts();
	}).catch(function(err){
		els.cartError.textContent = (err && err.message) ? err.message : 'Could not void sale.';
	});
}
function doCheckout(){
	if(!state.cart.length) return;
	els.cartError.textContent=''; els.checkoutBtn.disabled=true; els.checkoutBtn.textContent='Processing…';
	var payload={
		items: state.cart.map(function(item){ return {product_id:item.product_id, variant_id:item.variant_id, qty:item.qty}; }),
		customer_id: els.customerSelect.value||0,
		customer_type: (els.customerType&&els.customerType.value)||'b2c',
		discount_type: els.discountType.value,
		discount_amount: parseFloat(els.discountValue.value)||0,
		payment_method: els.paymentMethod.value,
		amount_paid: parseFloat(els.amountPaid.value)||0,
		tax_country: (els.taxCountry&&els.taxCountry.value)||window.SimplePOS.tax.country||'US',
		tax_state: (els.taxState&&els.taxState.value)||window.SimplePOS.tax.state||'',
	};
	apiFetch('/sales',{method:'POST', body: JSON.stringify(payload)}).then(function(sale){
		state.lastSaleId = sale.id || sale.sale_id || null;
		showReceipt(sale); clearCart(); loadProducts();
		els.checkoutBtn.disabled=true; els.checkoutBtn.textContent='Complete Sale';
		if(state.lastSaleId && window.SimplePOS && window.SimplePOS.caps && window.SimplePOS.caps.voidSales){
			if(els.voidLastBtn){ els.voidLastBtn.style.display=''; els.voidLastBtn.disabled=false; }
			if(els.voidReceiptBtn){ els.voidReceiptBtn.style.display=''; els.voidReceiptBtn.disabled=false; }
		}
	}).catch(function(err){ els.cartError.textContent=err.message||window.SimplePOS.i18n.checkoutError; }).finally(function(){ els.checkoutBtn.disabled=state.cart.length===0; els.checkoutBtn.textContent='Complete Sale'; });
}
function showReceipt(sale){
	var storeName=window.SimplePOS.storeName||'';
	var storeAddress=window.SimplePOS.storeAddress||'';
	var storePhone=window.SimplePOS.storePhone||'';
	var storeEmail=window.SimplePOS.storeEmail||'';
	var storeGstin=window.SimplePOS.storeGstin||'';
	var header=window.SimplePOS.receiptHeader||'';
	var footer=window.SimplePOS.receiptFooter||'';
	var cashierName=window.SimplePOS.cashierName||'';
	var isB2B = sale.customer_type === 'b2b';
	var itemsHtml=(sale.items||[]).map(function(item){
		var rate=parseFloat(item.price||0);
		var qty=parseInt(item.qty||0,10);
		var total=parseFloat(item.line_total||0);
		var tax=parseFloat(item.tax_amount||0);
		if(isB2B){
			var netLine=total-tax;
			var netRate=qty?netLine/qty:0;
			return '<tr>'
				+'<td class="simple-pos-receipt-item">'+escapeHtml(item.product_name)+(item.sku?'<br/><small>'+escapeHtml(item.sku)+'</small>':'')+'</td>'
				+'<td class="simple-pos-receipt-qty">'+qty+'</td>'
				+'<td class="simple-pos-receipt-amt">'+formatCurrency(netRate)+'</td>'
				+'<td class="simple-pos-receipt-amt">'+formatCurrency(tax)+'</td>'
				+'<td class="simple-pos-receipt-amt">'+formatCurrency(total)+'</td>'
				+'</tr>';
		}
		return '<tr>'
			+'<td class="simple-pos-receipt-item">'+escapeHtml(item.product_name)+(item.sku?'<br/><small>'+escapeHtml(item.sku)+'</small>':'')+'</td>'
			+'<td class="simple-pos-receipt-qty">'+qty+'</td>'
			+'<td class="simple-pos-receipt-amt">'+formatCurrency(rate)+'</td>'
			+'<td class="simple-pos-receipt-amt">'+formatCurrency(total)+'</td>'
			+'</tr>';
	}).join('');
	var breakdownHtml='';
	try{ var bd=JSON.parse(sale.tax_breakdown||'[]'); if(bd.length){ breakdownHtml=bd.map(function(b){ return '<tr><td colspan="'+(isB2B?4:3)+'">'+escapeHtml(b.name)+' @ '+b.rate+'%</td><td class="simple-pos-receipt-amt">'+formatCurrency(b.amount)+'</td></tr>'; }).join(''); } }catch(e){}
	var metaLines=[];
	if(storeGstin) metaLines.push(escapeHtml('GSTIN: '+storeGstin));
	metaLines.push(escapeHtml(sale.sale_number)+' | '+new Date(sale.created_at).toLocaleString());
	if(sale.tax_country||sale.tax_state) metaLines.push(escapeHtml((sale.tax_country||'')+' '+(sale.tax_state||'')));
	var html='<div class="simple-pos-receipt" style="width:'+(window.SimplePOS.paperWidth||'80mm')+'">'
		+'<div class="simple-pos-receipt-store">'
			+'<h2>'+escapeHtml(storeName)+'</h2>'
			+(storeAddress?'<p class="simple-pos-receipt-address">'+escapeHtml(storeAddress).replace(/\n/g,'<br/>')+'</p>':'')
			+(storePhone?'<p class="simple-pos-receipt-contact">Tel: '+escapeHtml(storePhone)+'</p>':'')
			+(storeEmail?'<p class="simple-pos-receipt-contact">'+escapeHtml(storeEmail)+'</p>':'')
		+'</div>'
		+(header?'<p class="simple-pos-receipt-header">'+escapeHtml(header)+'</p>':'')
		+'<div class="simple-pos-receipt-meta">'+metaLines.join('<br/>')+'</div>'
		+'<table class="simple-pos-receipt-table simple-pos-receipt-items">'
			+'<thead><tr><th>Item</th><th class="simple-pos-receipt-qty">Qty</th>'+(isB2B?'<th>Net</th><th>VAT</th>':'<th>Rate</th>')+'<th class="simple-pos-receipt-amt">'+(isB2B?'Gross':'Total')+'</th></tr></thead>'
			+'<tbody>'+itemsHtml+'</tbody>'
		+'</table>'
		+'<table class="simple-pos-receipt-table simple-pos-receipt-totals">'
			+'<tr><td colspan="'+(isB2B?4:3)+'">Subtotal</td><td class="simple-pos-receipt-amt">'+formatCurrency(sale.subtotal)+'</td></tr>'
			+'<tr><td colspan="'+(isB2B?4:3)+'">Discount</td><td class="simple-pos-receipt-amt">'+formatCurrency(sale.discount_amount)+'</td></tr>'
			+(breakdownHtml || '<tr><td colspan="'+(isB2B?4:3)+'">Tax</td><td class="simple-pos-receipt-amt">'+formatCurrency(sale.tax_amount)+'</td></tr>')
			+'<tr class="simple-pos-receipt-grand"><td colspan="'+(isB2B?4:3)+'">Grand Total</td><td class="simple-pos-receipt-amt">'+formatCurrency(sale.total)+'</td></tr>'
			+'<tr><td colspan="'+(isB2B?4:3)+'">Paid ('+escapeHtml(sale.payment_method)+')</td><td class="simple-pos-receipt-amt">'+formatCurrency(sale.amount_paid)+'</td></tr>'
			+'<tr><td colspan="'+(isB2B?4:3)+'">Change</td><td class="simple-pos-receipt-amt">'+formatCurrency(sale.change_due)+'</td></tr>'
		+'</table>'
		+(cashierName?'<p class="simple-pos-receipt-footer">Operator: '+escapeHtml(cashierName)+'</p>':'')
		+(footer?'<p class="simple-pos-receipt-footer">'+escapeHtml(footer)+'</p>':'')
	+'</div>';
	els.receiptContent.innerHTML=html;
	els.receiptModal.hidden=false;
	if(window.SimplePOS.autoKickDrawer) kickDrawer();
}
async function sendEscPos(bytes){
	if(!navigator.usb || !bytes) return false;
	try{
		var devices = (navigator.usb.getDevices) ? await navigator.usb.getDevices() : [];
		var dev = devices && devices.length ? devices[0] : null;
		if(!dev) return false;
		await dev.open();
		if(dev.configuration===null) await dev.selectConfiguration(1);
		await dev.claimInterface(0);
		var ep = dev.configuration.interfaces[0].alternate.interfaces[0].endpoints.find(function(e){return e.direction==='out';});
		if(ep) await dev.transferOut(ep.endpointNumber, bytes);
		return true;
	}catch(e){ try{ console.warn('[SimplePOS] WebUSB send failed:', e); }catch(_){} return false; }
}
async function usbPrint(){
	if(!els.receiptContent.innerHTML.trim()) return;
	var text=els.receiptContent.innerText;
	if(navigator.usb){
		var encoder=new TextEncoder();
		var init=new Uint8Array([0x1B,0x40]);
		var data=encoder.encode(text+"\n\n\n");
		var cut=new Uint8Array([0x1D,0x56,0x00]);
		var combined=new Uint8Array(init.length+data.length+cut.length);
		combined.set(init,0); combined.set(data,init.length); combined.set(cut,init.length+data.length);
		var ok=await sendEscPos(combined);
		if(ok){ return; }
	}
	window.print();
}
function kickDrawer(){
	sendEscPos(new Uint8Array([0x1B,0x70,0x00,0x19,0xFA]));
}
function handleScan(code){
	if(!code) return;
	apiFetch('/products/lookup/'+encodeURIComponent(code)).then(function(product){
		product = coerceProduct(product);
		if(product.variant_id){
			var existing=state.cart.find(function(i){ return i.variant_id===product.variant_id; });
			if(existing){ existing.qty+=1; } else {
				state.cart.push({product_id: product.parent_product_id || product.id, variant_id: product.variant_id, name: product.name, sku: product.sku, price: parseFloat(product.price), qty:1, stock_qty: product.stock_qty, track_stock: !!product.track_stock});
			}
			els.amountPaid.dataset.touched=''; totalsCache=null; renderCart();
		} else {
			apiFetch('/products/'+product.id).then(function(full){
				full = coerceProduct(full);
				if(full.variants && full.variants.length) { addProductToCart(full); } else { addProductToCart(product); }
			}).catch(function(){
				els.cartError.textContent = 'Could not load product details. Added with cached info only.';
				addProductToCart(coerceProduct(product));
			});
		}
		els.scanInput.value='';
	}).catch(function(err){
		els.cartError.textContent = (err && err.message) ? err.message : ('No product matches "'+code+'".');
		els.scanInput.value=''; els.scanInput.focus();
	});
}
function bindEvents(){
	els.scanInput.addEventListener('keydown',function(e){ if('Enter'===e.key){ e.preventDefault(); handleScan(els.scanInput.value.trim()); } });
	els.searchInput.addEventListener('input', debounce(function(){ state.search=els.searchInput.value.trim(); state.page=1; loadProducts(); },350));
	els.categoryTabs.addEventListener('click',function(e){ var btn=e.target.closest('.simple-pos-cat-tab'); if(!btn) return; state.activeCategory=parseInt(btn.dataset.cat,10)||0; state.page=1; renderCategoryTabs(); loadProducts(); });
	els.pagination.addEventListener('click',function(e){ var btn=e.target.closest('.simple-pos-page-btn'); if(!btn) return; state.page=parseInt(btn.dataset.page,10)||1; loadProducts(); });
	els.cartItems.addEventListener('click',function(e){
		var qtyBtn=e.target.closest('.simple-pos-qty-btn'); if(qtyBtn){ changeQty(parseInt(qtyBtn.dataset.index,10), 'inc'===qtyBtn.dataset.action?1:-1); return; }
		var removeBtn=e.target.closest('.simple-pos-cart-item-remove'); if(removeBtn){ removeItem(parseInt(removeBtn.dataset.index,10)); }
	});
	els.clearCartBtn.addEventListener('click',clearCart);
	if(els.holdBtn) els.holdBtn.addEventListener('click',holdCart);
	if(els.recallBtn) els.recallBtn.addEventListener('click',recallCart);
	if(els.voidLastBtn) els.voidLastBtn.addEventListener('click',function(){ voidLastSale(state.lastSaleId); });
	if(els.voidReceiptBtn) els.voidReceiptBtn.addEventListener('click',function(){ voidLastSale(state.lastSaleId); });
	els.discountValue.addEventListener('input',function(){ totalsCache=null; renderTotals(); });
	els.discountType.addEventListener('change',function(){ totalsCache=null; renderTotals(); });
	els.amountPaid.addEventListener('input',function(){ els.amountPaid.dataset.touched='1'; renderTotals(); });
	if(els.taxCountry) els.taxCountry.addEventListener('input',function(){ totalsCache=null; renderTotals(); });
	if(els.taxState) els.taxState.addEventListener('input',function(){ totalsCache=null; renderTotals(); });
	els.checkoutBtn.addEventListener('click',doCheckout);
	els.printReceiptBtn.addEventListener('click',function(){ window.print(); });
	if(els.usbPrintBtn) els.usbPrintBtn.addEventListener('click', usbPrint);
	if(els.kickDrawerBtn) els.kickDrawerBtn.addEventListener('click', kickDrawer);
	els.closeReceiptBtn.addEventListener('click',function(){ els.receiptModal.hidden=true; els.scanInput.focus(); });
	bindNewCustomer();
	bindAddToCart();
	// ESC to close modals, focus trap.
	document.addEventListener('keydown', function(e){
		if('Escape'===e.key){
			var vm=document.getElementById('simple-pos-variant-modal');
			if(vm && !vm.hidden){ vm.hidden=true; e.preventDefault(); return; }
			if(els.receiptModal && !els.receiptModal.hidden){ els.receiptModal.hidden=true; e.preventDefault(); return; }
			if(els.newCustomerModal && !els.newCustomerModal.hidden){
				els.newCustomerModal.hidden=true;
				e.preventDefault();
				if(els.newCustomerBtn) els.newCustomerBtn.focus();
				else if(els.scanInput) els.scanInput.focus();
				return;
			}
		}
	});
	// Product grid keyboard: Enter/Space on focused card.
	els.productGrid.addEventListener('keydown', function(e){
		if('Enter'===e.key || ' '===e.key){
			var card=e.target.closest('.simple-pos-product-card');
			if(card){ e.preventDefault(); card.click(); }
		}
	});
}
function init(){
	els.root=document.getElementById('simple-pos-terminal'); if(!els.root) return;
	els.scanInput=document.getElementById('simple-pos-scan-input');
	els.searchInput=document.getElementById('simple-pos-search-input');
	els.categoryTabs=document.getElementById('simple-pos-category-tabs');
	els.productGrid=document.getElementById('simple-pos-product-grid');
	els.pagination=document.getElementById('simple-pos-product-pagination');
	els.cartItems=document.getElementById('simple-pos-cart-items');
	els.clearCartBtn=document.getElementById('simple-pos-clear-cart');
	els.holdBtn=document.getElementById('simple-pos-hold-btn');
	els.recallBtn=document.getElementById('simple-pos-recall-btn');
	els.voidLastBtn=document.getElementById('simple-pos-void-last-btn');
	els.voidReceiptBtn=document.getElementById('simple-pos-void-receipt-btn');
	els.customerSelect=document.getElementById('simple-pos-customer-select');
	els.customerType=document.getElementById('simple-pos-customer-type');
	els.newCustomerBtn=document.getElementById('simple-pos-new-customer');
	els.newCustomerModal=document.getElementById('simple-pos-new-customer-modal');
	els.newCustomerName=document.getElementById('simple-pos-new-customer-name');
	els.newCustomerPhone=document.getElementById('simple-pos-new-customer-phone');
	els.newCustomerEmail=document.getElementById('simple-pos-new-customer-email');
	els.newCustomerError=document.getElementById('simple-pos-new-customer-error');
	els.newCustomerSave=document.getElementById('simple-pos-new-customer-save');
	els.newCustomerCancel=document.getElementById('simple-pos-new-customer-cancel');
	els.discountValue=document.getElementById('simple-pos-discount-value');
	els.discountType=document.getElementById('simple-pos-discount-type');
	els.paymentMethod=document.getElementById('simple-pos-payment-method');
	els.amountPaid=document.getElementById('simple-pos-amount-paid');
	els.taxCountry=document.getElementById('simple-pos-tax-country');
	els.taxState=document.getElementById('simple-pos-tax-state');
	els.subtotalEl=document.getElementById('simple-pos-subtotal');
	els.discountAmountEl=document.getElementById('simple-pos-discount-amount');
	els.taxEl=document.getElementById('simple-pos-tax');
	els.taxBreakdownEl=document.getElementById('simple-pos-tax-breakdown');
	els.totalEl=document.getElementById('simple-pos-total');
	els.changeDueEl=document.getElementById('simple-pos-change-due');
	els.checkoutBtn=document.getElementById('simple-pos-checkout-btn');
	els.cartError=document.getElementById('simple-pos-cart-error');
	els.receiptModal=document.getElementById('simple-pos-receipt-modal');
	els.receiptContent=document.getElementById('simple-pos-receipt-content');
	els.printReceiptBtn=document.getElementById('simple-pos-print-receipt');
	els.usbPrintBtn=document.getElementById('simple-pos-usb-print-receipt');
	els.kickDrawerBtn=document.getElementById('simple-pos-kick-drawer');
	els.closeReceiptBtn=document.getElementById('simple-pos-close-receipt');
	// variant modal already in DOM
	if(els.taxCountry) els.taxCountry.value=window.SimplePOS.tax.country||'US';
	if(els.taxState) els.taxState.value=window.SimplePOS.tax.state||'';
	bindEvents(); renderCart();
	try {
		var saved = localStorage.getItem('simple_pos_held_carts');
		if(saved){ state.heldCarts = JSON.parse(saved); }
	} catch(e){}
	var canVoid = window.SimplePOS && window.SimplePOS.caps && window.SimplePOS.caps.voidSales;
	if(els.voidLastBtn) els.voidLastBtn.style.display = canVoid ? '' : 'none';
	if(els.voidReceiptBtn) els.voidReceiptBtn.style.display = canVoid ? '' : 'none';
	updateHoldRecallButtons();
	Promise.all([
		safeLoad('categories', loadCategories),
		safeLoad('products',  loadProducts),
		safeLoad('customers', loadCustomers)
	]).then(function(){ els.root.dataset.loading='0'; els.scanInput.focus(); });
}
if('loading'===document.readyState) document.addEventListener('DOMContentLoaded',init); else init();
})();
