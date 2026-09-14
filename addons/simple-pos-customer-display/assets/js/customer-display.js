/**
 * Simple POS — Customer Display client.
 * Polls the REST endpoint and renders the latest cart state from the terminal.
 */
(function(){
'use strict';
var cfg = window.SimplePOSDisplay;
if(!cfg){ return; }

function money(n){
	var c = cfg.currency, v = Number(n||0).toFixed(c.decimals);
	return c.position === 'after' ? v + c.symbol : c.symbol + v;
}

function escapeHtml(s){
	return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
		return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
	});
}

var prev = '';

function render(state){
	var items = state.items || [];
	var totals = state.total || 0;

	// Cart items.
	var itemsBox = document.getElementById('scd-items');
	if(itemsBox){
		if(!items.length){
			itemsBox.innerHTML = '<div class="scd-empty">' + escapeHtml(cfg.i18n.empty) + '</div>';
		} else {
			var html = '<table class="scd-table">';
			items.forEach(function(it){
				html += '<tr><td class="scd-name">' + escapeHtml(it.name) + '</td><td class="scd-qty">×' + it.qty + '</td><td class="scd-price">' + money(it.total || (it.price * it.qty)) + '</td></tr>';
			});
			html += '</table>';
			itemsBox.innerHTML = html;
		}
	}

	// Totals block.
	var totalsBox = document.getElementById('scd-totals');
	if(totalsBox){
		if(!items.length){
			totalsBox.innerHTML = '';
			return;
		}
		var h = '';
		h += '<div class="scd-line"><span>' + escapeHtml(cfg.i18n.subtotal) + '</span><span>' + money(state.subtotal) + '</span></div>';
		if(state.discount > 0){ h += '<div class="scd-line scd-discount"><span>' + escapeHtml(cfg.i18n.discount) + '</span><span>−' + money(state.discount) + '</span></div>'; }
		if(state.tax > 0){ h += '<div class="scd-line"><span>' + escapeHtml(cfg.i18n.tax) + '</span><span>' + money(state.tax) + '</span></div>'; }
		h += '<div class="scd-line scd-total"><span>' + escapeHtml(cfg.i18n.total) + '</span><span>' + money(state.total) + '</span></div>';
		totalsBox.innerHTML = h;
	}
}

function poll(){
	fetch(cfg.restUrl + '/display/current')
		.then(function(r){ return r.json(); })
		.then(function(state){
			var json = JSON.stringify(state);
			if(json !== prev){
				prev = json;
				render(state);
			}
		})
		.catch(function(){});
}

poll();
setInterval(poll, cfg.refresh);
})();
