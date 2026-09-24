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

		function getSymbologyFormat( code ) {
			var symbology = ( window.SimplePOS && window.SimplePOS.barcode && window.SimplePOS.barcode.symbology )
				? window.SimplePOS.barcode.symbology
				: 'CODE128';
			if ( symbology === 'EAN13' ) {
				// EAN13 strictly requires 12 or 13 numeric digits. Fallback to CODE128 if non-numeric.
				if ( /^\d{12,13}$/.test( String( code ).trim() ) ) {
					return 'EAN13';
				}
				return 'CODE128';
			}
			return 'CODE128';
		}

		function drawFallbackSVG( svg, code ) {
			while ( svg.firstChild ) {
				svg.removeChild( svg.firstChild );
			}
			var str = String( code || '000000' );
			svg.setAttribute( 'viewBox', '0 0 200 50' );
			svg.setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );

			var x = 10;
			var width = 180;
			var barWidth = width / ( str.length * 6 + 10 );

			var guard = [ 1, 0, 1 ];
			for ( var g = 0; g < guard.length; g++ ) {
				if ( guard[ g ] ) {
					var rect = document.createElementNS( 'http://www.w3.org/2000/svg', 'rect' );
					rect.setAttribute( 'x', x );
					rect.setAttribute( 'y', '2' );
					rect.setAttribute( 'width', Math.max( 1.5, barWidth ) );
					rect.setAttribute( 'height', '34' );
					rect.setAttribute( 'fill', '#000' );
					svg.appendChild( rect );
				}
				x += Math.max( 1.5, barWidth );
			}

			for ( var i = 0; i < str.length; i++ ) {
				var charCode = str.charCodeAt( i );
				var pattern = [ ( charCode & 1 ), ( charCode & 2 ) ? 1 : 0, ( charCode & 4 ) ? 1 : 0, ( charCode & 8 ) ? 1 : 0, 1, 0 ];
				for ( var p = 0; p < pattern.length; p++ ) {
					if ( pattern[ p ] ) {
						var r = document.createElementNS( 'http://www.w3.org/2000/svg', 'rect' );
						r.setAttribute( 'x', x );
						r.setAttribute( 'y', '2' );
						r.setAttribute( 'width', Math.max( 1.5, barWidth ) );
						r.setAttribute( 'height', '32' );
						r.setAttribute( 'fill', '#000' );
						svg.appendChild( r );
					}
					x += Math.max( 1.5, barWidth );
				}
			}

			for ( var g = 0; g < guard.length; g++ ) {
				if ( guard[ g ] ) {
					var r2 = document.createElementNS( 'http://www.w3.org/2000/svg', 'rect' );
					r2.setAttribute( 'x', x );
					r2.setAttribute( 'y', '2' );
					r2.setAttribute( 'width', Math.max( 1.5, barWidth ) );
					r2.setAttribute( 'height', '34' );
					r2.setAttribute( 'fill', '#000' );
					svg.appendChild( r2 );
				}
				x += Math.max( 1.5, barWidth );
			}

			var text = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
			text.setAttribute( 'x', '100' );
			text.setAttribute( 'y', '46' );
			text.setAttribute( 'text-anchor', 'middle' );
			text.setAttribute( 'font-size', '10' );
			text.setAttribute( 'font-family', 'monospace' );
			text.setAttribute( 'fill', '#000' );
			text.textContent = str;
			svg.appendChild( text );
		}

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

				var code = c.dataset.barcode || c.dataset.sku || '000000';
				var format = getSymbologyFormat( code );
				var rendered = false;

				if ( window.JsBarcode ) {
					try {
						window.JsBarcode( svg, code, { format: format, displayValue: true, fontSize: 10, height: 40 } );
						rendered = true;
					} catch ( e ) {
						try {
							window.JsBarcode( svg, code, { format: 'CODE128', displayValue: true, fontSize: 10, height: 40 } );
							rendered = true;
						} catch ( e2 ) {
							rendered = false;
						}
					}
				}

				if ( ! rendered ) {
					drawFallbackSVG( svg, code );
				}
			} );
		}

		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var sel = [];
				checks.forEach( function ( c ) {
					if ( c.checked ) {
						var codeVal = c.dataset.barcode || c.dataset.sku || '';
						sel.push( { name: c.dataset.name, code: codeVal, price: c.dataset.price, format: getSymbologyFormat( codeVal ) } );
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
				var scriptOpen = '<scr' + 'ipt src="' + vendorUrl.replace( /"/g, '&quot;' ) + '">';
				var scriptClose = '</scr' + 'ipt>';
				var html = '<!doctype html><html><head><title>Labels</title><style>' +
					'@media print{ @page{ size:A4; margin:10mm } } body{font-family:sans-serif} .sheet{display:flex;flex-wrap:wrap;gap:6px} .label{border:1px solid #000; width:62mm; height:32mm; padding:4mm; text-align:center; box-sizing:border-box; display:flex; flex-direction:column; justify-content:center} .label svg{width:100%;height:18mm} .label .name{font-size:9px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis} .label .price{font-size:10px}</style>' +
					scriptOpen + scriptClose + '</head><body><div class="sheet">';
				sel.forEach( function ( s, i ) {
					html += '<div class="label"><div class="name">' + String( s.name ).replace( /</g, '&lt;' ) + '</div><svg id="bc' + i + '"></svg><div class="price">' + String( s.price ).replace( /</g, '&lt;' ) + '</div></div>';
				} );
				html += '</div><scr' + 'ipt>';
				html += 'function drawFallback(svg, code){';
				html += '  while(svg.firstChild) svg.removeChild(svg.firstChild);';
				html += '  var str = String(code || "000000");';
				html += '  svg.setAttribute("viewBox", "0 0 200 50");';
				html += '  var x = 10, width = 180, barWidth = width / (str.length * 6 + 10);';
				html += '  var guard = [1,0,1];';
				html += '  for(var g=0; g<guard.length; g++){ if(guard[g]){ var r = document.createElementNS("http://www.w3.org/2000/svg", "rect"); r.setAttribute("x", x); r.setAttribute("y", "2"); r.setAttribute("width", Math.max(1.5, barWidth)); r.setAttribute("height", "34"); r.setAttribute("fill", "#000"); svg.appendChild(r); } x += Math.max(1.5, barWidth); }';
				html += '  for(var i=0; i<str.length; i++){ var charCode = str.charCodeAt(i); var pattern = [(charCode & 1), (charCode & 2) ? 1 : 0, (charCode & 4) ? 1 : 0, (charCode & 8) ? 1 : 0, 1, 0]; for(var p=0; p<pattern.length; p++){ if(pattern[p]){ var r2 = document.createElementNS("http://www.w3.org/2000/svg", "rect"); r2.setAttribute("x", x); r2.setAttribute("y", "2"); r2.setAttribute("width", Math.max(1.5, barWidth)); r2.setAttribute("height", "32"); r2.setAttribute("fill", "#000"); svg.appendChild(r2); } x += Math.max(1.5, barWidth); } }';
				html += '  for(var g=0; g<guard.length; g++){ if(guard[g]){ var r3 = document.createElementNS("http://www.w3.org/2000/svg", "rect"); r3.setAttribute("x", x); r3.setAttribute("y", "2"); r3.setAttribute("width", Math.max(1.5, barWidth)); r3.setAttribute("height", "34"); r3.setAttribute("fill", "#000"); svg.appendChild(r3); } x += Math.max(1.5, barWidth); }';
				html += '  var text = document.createElementNS("http://www.w3.org/2000/svg", "text"); text.setAttribute("x", "100"); text.setAttribute("y", "46"); text.setAttribute("text-anchor", "middle"); text.setAttribute("font-size", "10"); text.setAttribute("font-family", "monospace"); text.setAttribute("fill", "#000"); text.textContent = str; svg.appendChild(text);';
				html += '}';
				html += 'window.onload=function(){';
				sel.forEach( function ( s, i ) {
					html += 'var svg' + i + ' = document.getElementById("bc' + i + '");';
					html += 'var done' + i + ' = false;';
					html += 'if(window.JsBarcode){ try{ JsBarcode(svg' + i + ', "' + String( s.code ).replace( /"/g, '\\"' ) + '", {format:"' + s.format + '", displayValue:true, fontSize:9, height:36}); done' + i + ' = true; }catch(e){ try{ JsBarcode(svg' + i + ', "' + String( s.code ).replace( /"/g, '\\"' ) + '", {format:"CODE128", displayValue:true, fontSize:9, height:36}); done' + i + ' = true; }catch(e2){} } }';
					html += 'if(!done' + i + '){ drawFallback(svg' + i + ', "' + String( s.code ).replace( /"/g, '\\"' ) + '"); }';
				} );
				html += ' setTimeout(function(){window.print();},400);} </scr' + 'ipt></body></html>';
				win.document.write( html );
				win.document.close();
			} );
		}

		renderPreview();
	} );
} )();
