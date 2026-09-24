/**
 * Simple POS — Barcode Labels screen.
 *
 * Enqueued via wp_enqueue_script() on the Barcode Labels admin screen only
 * (see Simple_POS_Admin::enqueue_assets()). Preview rendering + print-window
 * generation for A4/roll label sheets.
 *
 * Uses the locally-vendored JsBarcode copy (admin/js/vendor/jsbarcode.min.js)
 * as primary, with a CDN fallback if the local copy fails to load or render.
 * The print window tries both sources so labels still print in degraded mode.
 */
( function () {
	'use strict';

	var CDN_URL = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';

	function loadScript( url ) {
		return new Promise( function ( resolve, reject ) {
			var s = document.createElement( 'script' );
			s.src = url;
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild( s );
		} );
	}

	function ensureJsBarcode() {
		if ( window.JsBarcode ) {
			return Promise.resolve();
		}
		var vendorUrl = window.SimplePOSVendorUrl || '';
		var wrap = document.getElementById( 'pos-label-print' );
		if ( ! vendorUrl && wrap && wrap.dataset && wrap.dataset.vendorUrl ) {
			vendorUrl = wrap.dataset.vendorUrl;
		}
		if ( vendorUrl ) {
			return loadScript( vendorUrl );
		}
		return loadScript( CDN_URL );
	}

	function getSymbologyFormat( code ) {
		var symbology = ( window.SimplePOS && window.SimplePOS.barcode && window.SimplePOS.barcode.symbology )
			? window.SimplePOS.barcode.symbology
			: 'CODE39';
		if ( symbology === 'EAN13' ) {
			if ( /^\d{12,13}$/.test( String( code ).trim() ) ) {
				return 'EAN13';
			}
			return 'CODE39';
		}
		return 'CODE39';
	}

	function renderBarcode( svg, code, format ) {
		var renderCode = format === 'CODE39' ? String( code ).toUpperCase() : code;
		try {
			window.JsBarcode( svg, renderCode, { format: format, displayValue: true, fontSize: 10, height: 40 } );
			return true;
		} catch ( e ) {
			try {
				window.JsBarcode( svg, renderCode, { format: 'CODE39', displayValue: true, fontSize: 10, height: 40 } );
				return true;
			} catch ( e2 ) {
				return false;
			}
		}
	}

	function renderPreview( checks ) {
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

			var code = c.dataset.barcode || c.dataset.sku || '000000';
			var format = getSymbologyFormat( code );

			if ( ! window.JsBarcode ) {
				ensureJsBarcode().then( function () {
					if ( ! renderBarcode( svg, code, format ) ) {
						showUnavailable( svg, code );
					}
				} ).catch( function () {
					showUnavailable( svg, code );
				} );
				return;
			}

			if ( ! renderBarcode( svg, code, format ) ) {
				showUnavailable( svg, code );
			}
		} );
	}

	function showUnavailable( svg, code ) {
		var text = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
		text.setAttribute( 'x', '100' );
		text.setAttribute( 'y', '25' );
		text.setAttribute( 'text-anchor', 'middle' );
		text.setAttribute( 'font-size', '11' );
		text.setAttribute( 'fill', '#b00' );
		text.textContent = 'Barcode preview unavailable';
		svg.appendChild( text );

		text = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
		text.setAttribute( 'x', '100' );
		text.setAttribute( 'y', '42' );
		text.setAttribute( 'text-anchor', 'middle' );
		text.setAttribute( 'font-size', '10' );
		text.setAttribute( 'font-family', 'monospace' );
		text.setAttribute( 'fill', '#000' );
		text.textContent = String( code || '000000' );
		svg.appendChild( text );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var checks = document.querySelectorAll( '.pos-label-check' );
		var selAll = document.getElementById( 'pos-select-all' );
		var btn = document.getElementById( 'pos-print-labels' );

		if ( selAll ) {
			selAll.addEventListener( 'change', function () {
				checks.forEach( function ( c ) {
					c.checked = selAll.checked;
				} );
				renderPreview( checks );
			} );
		}

		checks.forEach( function ( c ) {
			c.addEventListener( 'change', function () {
				renderPreview( checks );
			} );
		} );

		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var sel = [];
				checks.forEach( function ( c ) {
					if ( c.checked ) {
						var codeVal = c.dataset.barcode || c.dataset.sku || '';
						var codeFormat = getSymbologyFormat( codeVal );
						if ( codeFormat === 'CODE39' ) {
							codeVal = String( codeVal ).toUpperCase();
						}
						sel.push( { name: c.dataset.name, code: codeVal, price: c.dataset.price, format: codeFormat } );
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
			var scriptTags = '<scr' + 'ipt src="' + CDN_URL.replace( /"/g, '&quot;' ) + '"></scr' + 'ipt>';
			if ( vendorUrl ) {
				scriptTags = '<scr' + 'ipt src="' + vendorUrl.replace( /"/g, '&quot;' ) + '"></scr' + 'ipt>' + scriptTags;
			}
			var html = '<!doctype html><html><head><title>Labels</title><style>' +
				'@media print{ @page{ size:A4; margin:10mm } } body{font-family:sans-serif} .sheet{display:flex;flex-wrap:wrap;gap:6px} .label{border:1px solid #000; width:62mm; height:32mm; padding:4mm; text-align:center; box-sizing:border-box; display:flex; flex-direction:column; justify-content:center} .label svg{width:100%;height:18mm} .label .name{font-size:9px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis} .label .price{font-size:10px}</style>' +
				scriptTags + '</head><body><div class="sheet">';
				sel.forEach( function ( s, i ) {
					html += '<div class="label"><div class="name">' + String( s.name ).replace( /</g, '&lt;' ) + '</div><svg id="bc' + i + '"></svg><div class="price">' + String( s.price ).replace( /</g, '&lt;' ) + '</div></div>';
				} );
				html += '</div><scr' + 'ipt>';
				html += 'function renderBarcode(svg, code, format){';
				html += '  var renderCode = format === "CODE39" ? String(code).toUpperCase() : code;';
				html += '  if(window.JsBarcode){ try{ JsBarcode(svg, renderCode, {format:format, displayValue:true, fontSize:9, height:36}); return true; }catch(e){ try{ JsBarcode(svg, renderCode, {format:"CODE39", displayValue:true, fontSize:9, height:36}); return true; }catch(e2){} } }';
				html += '  return false;';
				html += '}';
				html += 'window.onload=function(){';
				sel.forEach( function ( s, i ) {
					html += 'var svg' + i + ' = document.getElementById("bc' + i + '");';
					html += 'if(!renderBarcode(svg' + i + ', "' + String( s.code ).replace( /"/g, '\\"' ) + '", "' + s.format + '")){ var text=document.createElementNS("http://www.w3.org/2000/svg","text"); text.setAttribute("x","100"); text.setAttribute("y","30"); text.setAttribute("text-anchor","middle"); text.setAttribute("font-size","12"); text.setAttribute("fill","#c00"); text.textContent="Barcode preview unavailable"; svg' + i + '.appendChild(text); }';
				} );
				html += ' setTimeout(function(){window.print();},400);} </scr' + 'ipt></body></html>';
				win.document.write( html );
				win.document.close();
			} );
		}

		renderPreview( checks );
	} );
} )();
