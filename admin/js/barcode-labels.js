/**
 * Simple POS — Barcode Labels screen.
 *
 * Handles preview rendering and print-window generation for barcode labels.
 * Renders barcodes using JsBarcode in the main document context and embeds
 * the generated SVG markup directly into the printable window.
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

		function generateBarcodeSVG( code ) {
			var svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
			svg.style.width = '100%';
			svg.style.height = '100%';
			var format = getSymbologyFormat( code );
			var cleanCode = String( code || '000000' ).trim();

			if ( window.JsBarcode ) {
				try {
					window.JsBarcode( svg, cleanCode, {
						format: format,
						displayValue: true,
						fontSize: 12,
						height: 40,
						margin: 2
					} );
					return svg;
				} catch ( e ) {
					try {
						window.JsBarcode( svg, cleanCode, {
							format: 'CODE128',
							displayValue: true,
							fontSize: 12,
							height: 40,
							margin: 2
						} );
						return svg;
					} catch ( e2 ) {
						// Fallback if JsBarcode fails entirely.
					}
				}
			}

			// Basic fallback if JsBarcode is unavailable.
			var text = document.createElementNS( 'http://www.w3.org/2000/svg', 'text' );
			text.setAttribute( 'x', '50%' );
			text.setAttribute( 'y', '50%' );
			text.setAttribute( 'text-anchor', 'middle' );
			text.setAttribute( 'font-size', '14' );
			text.textContent = cleanCode;
			svg.appendChild( text );
			return svg;
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
				var code = c.dataset.barcode || c.dataset.sku || '000000';
				var svg = generateBarcodeSVG( code );
				var price = document.createElement( 'div' );
				price.textContent = c.dataset.price;
				price.style.fontSize = '10px';
				div.appendChild( name );
				div.appendChild( svg );
				div.appendChild( price );
				container.appendChild( div );
			} );
		}

		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var sel = [];
				checks.forEach( function ( c ) {
					if ( c.checked ) {
						var codeVal = c.dataset.barcode || c.dataset.sku || '';
						sel.push( {
							name: c.dataset.name,
							code: codeVal,
							price: c.dataset.price,
							svgHTML: generateBarcodeSVG( codeVal ).outerHTML
						} );
					}
				} );
				if ( ! sel.length ) {
					window.alert( 'Select at least one' );
					return;
				}
				var win = window.open( '', '_blank' );
				if ( ! win ) {
					return;
				}
				var html = '<!doctype html><html><head><title>Barcode Labels</title><style>' +
					'@media print{ @page{ size:A4; margin:10mm } } ' +
					'body{ font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; margin:0; padding:10px; } ' +
					'.sheet{ display:flex; flex-wrap:wrap; gap:8px; } ' +
					'.label{ border:1px solid #000; width:62mm; height:32mm; padding:3mm; text-align:center; box-sizing:border-box; display:flex; flex-direction:column; justify-content:space-between; align-items:center; page-break-inside:avoid; } ' +
					'.label .name{ font-size:11px; font-weight:600; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; } ' +
					'.label .barcode-wrap{ width:100%; height:18mm; display:flex; justify-content:center; align-items:center; } ' +
					'.label .barcode-wrap svg{ max-width:100%; max-height:100%; } ' +
					'.label .price{ font-size:11px; font-weight:600; }' +
					'</style></head><body><div class="sheet">';
				sel.forEach( function ( s ) {
					html += '<div class="label">' +
						'<div class="name">' + String( s.name ).replace( /</g, '&lt;' ) + '</div>' +
						'<div class="barcode-wrap">' + s.svgHTML + '</div>' +
						'<div class="price">' + String( s.price ).replace( /</g, '&lt;' ) + '</div>' +
						'</div>';
				} );
				html += '</div><script>window.onload=function(){ setTimeout(function(){ window.print(); }, 200); };</script></body></html>';
				win.document.write( html );
				win.document.close();
			} );
		}

		renderPreview();
	} );
} )();
