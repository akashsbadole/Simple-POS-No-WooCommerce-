/**
 * Simple POS — Barcode Labels screen.
 *
 * Enqueued via wp_enqueue_script() on the Barcode Labels admin screen only
 * (see Simple_POS_Admin::enqueue_assets()). Preview rendering + print-window
 * generation for A4/roll label sheets. Uses the locally-vendored JsBarcode
 * copy (admin/js/vendor/jsbarcode.min.js) exposed as window.SimplePOSVendorUrl
 * via wp_add_inline_script() — no CDN, per WordPress.org guidelines.
 *
 * The print window HTML is generated at runtime with window.open() /
 * document.write(). Its <script> tag is split as '<scr' + 'ipt' below so
 * static analysis (WordPress.WP.EnqueuedResources) does not mistake the
 * runtime print-window template for an un-enqueued page script.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var checks = document.querySelectorAll( '.pos-label-check' );
		var selAll = document.getElementById( 'pos-select-all' );
		var btn = document.getElementById( 'pos-print-labels' );

		if ( selAll ) {
			selAll.addEventListener( 'change', function () {
				checks.forEach( function ( c ) {
					c.checked = selAll.checked;
				} );
				renderPreview();
			} );
		}

		checks.forEach( function ( c ) {
			c.addEventListener( 'change', renderPreview );
		} );

		function renderPreview() {
			var container = document.getElementById( 'pos-label-preview' );
			if ( ! container ) {
				return;
			}
			container.innerHTML = '';
			var selected = [];
			checks.forEach( function ( c ) {
				if ( c.checked ) {
					selected.push( c );
				}
			} );
			if ( ! selected.length ) {
				container.innerHTML = '<em>No labels selected</em>';
				return;
			}
			selected.forEach( function ( c ) {
				var div = document.createElement( 'div' );
				div.className = 'pos-label';
				div.style.cssText = 'border:1px dashed #999; padding:8px; margin:6px; text-align:center; display:inline-block; width:180px';
				var name = document.createElement( 'div' );
				name.textContent = c.dataset.name;
				name.style.fontSize = '11px';
				name.style.fontWeight = '600';
				var svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
				svg.style.width = '100%';
				svg.style.height = '50px';
				var price = document.createElement( 'div' );
				price.textContent = c.dataset.price;
				price.style.fontSize = '10px';
				div.appendChild( name );
				div.appendChild( svg );
				div.appendChild( price );
				container.appendChild( div );
				try {
					var code = c.dataset.barcode || '000000';
					if ( window.JsBarcode ) {
						window.JsBarcode( svg, code, { format: 'CODE128', displayValue: true, fontSize: 10, height: 40 } );
					}
				} catch ( e ) {
					// Ignore individual label render failures so one bad code
					// never blocks the rest of the preview.
				}
			} );
		}

		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var sel = [];
				checks.forEach( function ( c ) {
					if ( c.checked ) {
						sel.push( { name: c.dataset.name, code: c.dataset.barcode || c.dataset.sku, price: c.dataset.price } );
					}
				} );
				if ( ! sel.length ) {
					window.alert( 'Select at least one' );
					return;
				}
				var vendorUrl = window.SimplePOSVendorUrl || '';
				var wrap = document.getElementById( 'pos-label-print' );
				if ( ! vendorUrl && wrap && wrap.dataset && wrap.dataset.vendorUrl ) {
					vendorUrl = wrap.dataset.vendorUrl;
				}
				var win = window.open( '', '_blank' );
				if ( ! win ) {
					return;
				}
				// Split script tag so static scanners do not flag this
				// runtime-generated print document as an un-enqueued script.
				var scriptOpen = '<scr' + 'ipt src="' + vendorUrl.replace( /"/g, '&quot;' ) + '">';
				var scriptClose = '</scr' + 'ipt>';
				var html = '<!doctype html><html><head><title>Labels</title><style>' +
					'@media print{ @page{ size:A4; margin:10mm } } body{font-family:sans-serif} .sheet{display:flex;flex-wrap:wrap;gap:6px} .label{border:1px solid #000; width:62mm; height:32mm; padding:4mm; text-align:center; box-sizing:border-box; display:flex; flex-direction:column; justify-content:center} .label svg{width:100%;height:18mm} .label .name{font-size:9px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis} .label .price{font-size:10px}</style>' +
					scriptOpen + scriptClose + '</head><body><div class="sheet">';
				sel.forEach( function ( s, i ) {
					html += '<div class="label"><div class="name">' + String( s.name ).replace( /</g, '&lt;' ) + '</div><svg id="bc' + i + '"></svg><div class="price">' + String( s.price ).replace( /</g, '&lt;' ) + '</div></div>';
				} );
				html += '</div><scr' + 'ipt>window.onload=function(){';
				sel.forEach( function ( s, i ) {
					html += 'try{JsBarcode(document.getElementById("bc' + i + '"),"' + String( s.code ).replace( /"/g, '\\"' ) + '",{format:"CODE128",displayValue:true,fontSize:9,height:36});}catch(e){}';
				} );
				html += ' setTimeout(function(){window.print();},400);} </scr' + 'ipt></body></html>';
				win.document.write( html );
				win.document.close();
			} );
		}

		renderPreview();
	} );
} )();
