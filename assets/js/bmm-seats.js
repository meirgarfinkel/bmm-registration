/**
 * BMM Registration — "Same for all davenings" toggle logic.
 */
( function () {
	'use strict';

	function init() {
		const toggle = document.getElementById( 'bmm_same_for_all' );
		if ( ! toggle ) return;

		const sameRow   = document.getElementById( 'bmm-seats-same-row' );
		const gridRows  = document.getElementById( 'bmm-seats-grid' );
		const sameMen   = document.getElementById( 'bmm_same_men' );
		const sameWomen = document.getElementById( 'bmm_same_women' );

		toggle.addEventListener( 'change', function () {
			const on = this.checked;
			sameRow.hidden  = ! on;
			gridRows.hidden = on;

			if ( on ) {
				// Sync current single values into all davening inputs
				syncAllFromSame( sameMen.value, sameWomen.value );
			}
		} );

		if ( sameMen )   sameMen.addEventListener(   'input', () => syncAllFromSame( sameMen.value, null ) );
		if ( sameWomen ) sameWomen.addEventListener( 'input', () => syncAllFromSame( null, sameWomen.value ) );
	}

	function syncAllFromSame( menVal, womenVal ) {
		const cfg = window.bmmConfig || {};
		( cfg.davenings || [] ).forEach( key => {
			if ( menVal !== null ) {
				const el = document.querySelector( `[name="seats_men[${ key }]"]` );
				if ( el ) el.value = Math.max( 0, parseInt( menVal, 10 ) || 0 );
			}
			if ( womenVal !== null ) {
				const el = document.querySelector( `[name="seats_women[${ key }]"]` );
				if ( el ) el.value = Math.max( 0, parseInt( womenVal, 10 ) || 0 );
			}
		} );

		// Notify pricing module
		document.dispatchEvent( new Event( 'bmm:seats-changed' ) );
	}

	// Test hook (inert in the browser, where `module` is undefined).
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { init };
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
