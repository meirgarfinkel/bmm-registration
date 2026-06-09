/**
 * BMM Registration — live price preview.
 * Calls the /bmm/v1/calculate-price REST endpoint and updates the UI.
 */
( function () {
	'use strict';

	const cfg = window.bmmConfig || {};
	let debounceTimer = null;

	function init() {
		const wrap = document.getElementById( 'bmm-registration' );
		if ( ! wrap ) return;

		// Listen for changes on seats, membership checkbox, and sponsorships
		wrap.addEventListener( 'change', scheduleUpdate );
		wrap.addEventListener( 'input',  scheduleUpdate );
		document.addEventListener( 'bmm:seats-changed', scheduleUpdate );
	}

	function scheduleUpdate() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( fetchPrice, 350 );
	}

	async function fetchPrice() {
		if ( ! cfg.priceEndpoint || ! cfg.formId ) return;

		const state  = window.bmmState;
		const wrap   = document.getElementById( 'bmm-registration' );
		if ( ! wrap ) return;

		// Collect current values directly from DOM (live, before state.formData is committed)
		const wantsMembership = wrap.querySelector( '#bmm_wants_membership' )?.checked || false;

		const seatsMen   = {};
		const seatsWomen = {};
		( cfg.davenings || [] ).forEach( key => {
			const m = wrap.querySelector( `[name="seats_men[${ key }]"]` );
			const w = wrap.querySelector( `[name="seats_women[${ key }]"]` );
			seatsMen[ key ]   = m ? Math.max( 0, parseInt( m.value, 10 ) || 0 ) : 0;
			seatsWomen[ key ] = w ? Math.max( 0, parseInt( w.value, 10 ) || 0 ) : 0;
		} );

		const sponsorshipIds = Array.from(
			wrap.querySelectorAll( '.bmm-sponsorship-check:checked' )
		).map( el => el.value );

		try {
			const res = await fetch( cfg.priceEndpoint, {
				method:  'POST',
				headers: { 'Content-Type': 'application/json' },
				body:    JSON.stringify( {
					form_id:          cfg.formId,
					wants_membership: wantsMembership,
					seats_men:        seatsMen,
					seats_women:      seatsWomen,
					sponsorship_ids:  sponsorshipIds,
				} ),
			} );

			if ( ! res.ok ) return;
			const data = await res.json();
			updateUI( data );
		} catch ( e ) {}
	}

	function updateUI( data ) {
		show( 'bmm-preview-membership',       data.membership > 0 );
		show( 'bmm-preview-extra-men',        data.extra_men_seats > 0 );
		show( 'bmm-preview-extra-women',      data.extra_women_seats > 0 );
		show( 'bmm-preview-sponsorships',     data.sponsorships_total > 0 );

		setText( 'bmm-preview-membership-amount',    data.membership > 0    ? '₪' + data.membership    : '' );
		setText( 'bmm-preview-extra-men-label',      `Extra men's seats (×${ data.extra_men_count })` );
		setText( 'bmm-preview-extra-men-amount',     data.extra_men_seats   ? '₪' + data.extra_men_seats   : '' );
		setText( 'bmm-preview-extra-women-label',    `Extra women's seats (×${ data.extra_women_count })` );
		setText( 'bmm-preview-extra-women-amount',   data.extra_women_seats ? '₪' + data.extra_women_seats : '' );
		setText( 'bmm-preview-sponsorships-amount',  data.sponsorships_total ? '₪' + data.sponsorships_total : '' );
	}

	function show( id, visible ) {
		const el = document.getElementById( id );
		if ( el ) el.hidden = ! visible;
	}

	function setText( id, text ) {
		const el = document.getElementById( id );
		if ( el ) el.textContent = text;
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
