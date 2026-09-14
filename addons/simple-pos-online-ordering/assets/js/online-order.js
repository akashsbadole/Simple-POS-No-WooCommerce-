/**
 * Simple POS — Online Ordering storefront client.
 * Renders the localized product catalog into a category-filterable list,
 * keeps the order in localStorage, and posts it to the REST endpoint.
 */
(function(){
'use strict';
var cfg = window.SimplePOSOnline;
if(!cfg){ return; }

var state = { cat: 0, cart: [] };

function money(n){
	var c = cfg.currency, v = Number(n||0).toFixed(c.decimals);
	return c.position === 'after' ? v + c.symbol : c.symbol + v;
}

function loadCart(){
	try{ state.cart = JSON.parse(localStorage.getItem('simple_pos_online_cart') || '[]') || []; }
	catch(e){ state.cart = []; }
}

function saveCart(){
	try{ localStorage.setItem('simple_pos_online_cart', JSON.stringify(state.cart)); }
	catch(e){}
	renderCart();
}

function renderProducts(){
	var wrap = document.getElementById('simple-pos-online-products');
	if(!wrap){ return; }
	var list = state.cat
		? cfg.catalog.products.filter(function(p){ return p.category_id === state.cat; })
		: cfg.catalog.products;
	wrap.innerHTML = '';
	if(!list.length){
		wrap.innerHTML = '<p class="simple-pos-muted">' + cfg.i18n.empty + '</p>';
		return;
	}
	list.forEach(function(p){
		var card = document.createElement('div');
		card.className = 'simple-pos-store-product';
		card.innerHTML =
			'<div class="simple-pos-store-name">' + escapeHtml(p.name) + '</div>' +
			'<div class="simple-pos-store-price">' + money(p.price) + '</div>' +
			'<button type="button" disabled="' + (p.has_variants ? 'true' : 'false') + '" data-add="' + p.id + '">' +
				(p.has_variants ? cfg.i18n.pickVariant || 'Pick at counter' : cfg.i18n.add) + '</button>';
		var addBtn = card.querySelector('[data-add]');
		if(!p.has_variants){
			addBtn.disabled = false;
			addBtn.addEventListener('click', function(){ addToCart(p); });
		}
		wrap.appendChild(card);
	});
}

function addToCart(p){
	var found = state.cart.filter(function(l){ return l.product_id === p.id; })[0];
	if(found){ found.qty++; } else { state.cart.push({ product_id: p.id, name: p.name, price: p.price, qty: 1 }); }
	saveCart();
}

function renderCart(){
	var box = document.getElementById('simple-pos-online-cart-items');
	var total = document.getElementById('simple-pos-online-total');
	if(!box){ return; }
	box.innerHTML = '';
	var sum = 0;
	state.cart.forEach(function(l, i){
		sum += l.price * l.qty;
		var row = document.createElement('div');
		row.className = 'simple-pos-online-line';
		row.innerHTML =
			'<span>' + escapeHtml(l.name) + ' × ' + l.qty + '</span>' +
			'<strong>' + money(l.price * l.qty) + '</strong>' +
			'<button type="button" data-remove="' + i + '" aria-label="Remove">×</button>';
		row.querySelector('[data-remove]').addEventListener('click', function(){
			state.cart.splice(i, 1); saveCart();
		});
		box.appendChild(row);
	});
	if(total){ total.textContent = money(sum); }
	if(!state.cart.length){
		box.innerHTML = '<p class="simple-pos-muted">' + cfg.i18n.cartEmpty || '' + '</p>';
	}
}

function postOrder(ev){
	ev.preventDefault();
	var msg = document.getElementById('simple-pos-online-message');
	if(!state.cart.length){
		if(msg){ msg.textContent = cfg.i18n.empty; }
		return;
	}
	var btn = ev.target.querySelector('button[type=submit]');
	if(btn){ btn.disabled = true; }
	fetch(cfg.restUrl + '/online-orders', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			customer_name: (document.getElementById('soo-name')||{}).value || '',
			customer_phone: (document.getElementById('soo-phone')||{}).value || '',
			customer_email: (document.getElementById('soo-email')||{}).value || '',
			delivery_address: (document.getElementById('soo-address')||{}).value || '',
			items: state.cart.map(function(l){ return { product_id: l.product_id, name: l.name, price: l.price, qty: l.qty }; })
		})
	}).then(function(res){ return res.json().then(function(b){ return { ok: res.ok, body: b }; }); })
	.then(function(r){
		if(msg){
			msg.textContent = r.ok ? cfg.i18n.placed : ((r.body && r.body.message) || cfg.i18n.failed);
			msg.style.color = r.ok ? '#2e7d32' : '#c62828';
		}
		if(r.ok){
			state.cart = [];
			try{ localStorage.removeItem('simple_pos_online_cart'); }catch(e){}
			renderCart();
			if(ev.target){ ev.target.reset(); }
			if(btn){ btn.disabled = false; }
		}
	})
	.catch(function(){
		if(msg){ msg.textContent = cfg.i18n.failed; }
		if(btn){ btn.disabled = false; }
	});
}

function escapeHtml(s){
	return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
		return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
	});
}

function bind(el, evt, fn){
	var n = document.getElementById(el);
	if(n){ n.addEventListener(evt, fn); }
}

bind('simple-pos-online-checkout', 'submit', postOrder);

document.addEventListener('DOMContentLoaded', function(){
	loadCart();
	renderProducts();
	renderCart();
	document.querySelectorAll('#simple-pos-online-store .simple-pos-online-cats button').forEach(function(b){
		b.addEventListener('click', function(){
			document.querySelectorAll('#simple-pos-online-store .simple-pos-online-cats button').forEach(function(x){ x.classList.remove('is-active'); });
			b.classList.add('is-active');
			state.cat = parseInt(b.getAttribute('data-cat'), 10) || 0;
			renderProducts();
		});
	});
});
})();