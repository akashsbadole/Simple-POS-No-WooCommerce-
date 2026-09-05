/**
 * Simple POS — shared admin screen helpers (not the Terminal).
 * Keeps the classic server-rendered admin screens snappy without needing
 * a build step: auto-select-on-focus for numeric adjustment fields, and
 * confirmation is otherwise handled inline via onclick attributes in the
 * PHP views (kept there so the confirmation text stays translatable).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Select all text when focusing "quick restock" style numeric deltas,
		// so typing a new value doesn't require manually clearing the field.
		document.querySelectorAll( 'input[name="delta"]' ).forEach( function ( input ) {
			input.addEventListener( 'focus', function () {
				input.select();
			} );
		} );

		// Auto-focus the search box on list screens, if present and empty,
		// so staff can start typing immediately after the page loads.
		var search = document.querySelector( '.simple-pos-filters input[type="search"]' );
		if ( search && ! search.value ) {
			search.focus();
		}
	} );
} )();
