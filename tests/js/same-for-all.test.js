'use strict';

/**
 * Tests for the "Same for all davenings" sync in assets/js/bmm-seats.js.
 *
 * When the toggle is on (or the shared inputs change), the single men's/women's
 * value must be mirrored into every per-davening input, and a bmm:seats-changed
 * event fired so live pricing refreshes.
 */

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { loadSeats, DAVENINGS } = require( './_support.js' );

function seatValues( doc, gender ) {
	return DAVENINGS.map( ( key ) =>
		doc.querySelector( `[name="seats_${ gender }[${ key }]"]` ).value
	);
}

test( 'typing a shared women value mirrors it into every davening', () => {
	const { dom, doc } = loadSeats();
	const sameWomen = doc.getElementById( 'bmm_same_women' );

	sameWomen.value = '4';
	sameWomen.dispatchEvent( new dom.window.Event( 'input' ) );

	assert.deepEqual( seatValues( doc, 'women' ), [ '4', '4', '4', '4', '4', '4' ] );
	// Men are untouched by a women-only change.
	assert.deepEqual( seatValues( doc, 'men' ), [ '0', '0', '0', '0', '0', '0' ] );
} );

test( 'shared men and women sync independently', () => {
	const { dom, doc } = loadSeats();
	const sameMen = doc.getElementById( 'bmm_same_men' );
	sameMen.value = '2';
	sameMen.dispatchEvent( new dom.window.Event( 'input' ) );

	assert.deepEqual( seatValues( doc, 'men' ), [ '2', '2', '2', '2', '2', '2' ] );
	assert.deepEqual( seatValues( doc, 'women' ), [ '0', '0', '0', '0', '0', '0' ] );
} );

test( 'a shared change fires bmm:seats-changed for live pricing', () => {
	const { dom, doc } = loadSeats();
	let fired = 0;
	doc.addEventListener( 'bmm:seats-changed', () => { fired++; } );

	const sameWomen = doc.getElementById( 'bmm_same_women' );
	sameWomen.value = '3';
	sameWomen.dispatchEvent( new dom.window.Event( 'input' ) );

	assert.ok( fired >= 1, 'bmm:seats-changed should fire on a shared-value change' );
} );

test( 'toggling "Same for all" on shows the shared row and syncs current values', () => {
	const { dom, doc } = loadSeats();
	const toggle   = doc.getElementById( 'bmm_same_for_all' );
	const sameRow  = doc.getElementById( 'bmm-seats-same-row' );
	const grid     = doc.getElementById( 'bmm-seats-grid' );

	// Pre-fill the shared inputs, then flip the toggle on.
	doc.getElementById( 'bmm_same_men' ).value = '1';
	doc.getElementById( 'bmm_same_women' ).value = '5';
	toggle.checked = true;
	toggle.dispatchEvent( new dom.window.Event( 'change' ) );

	assert.equal( sameRow.hidden, false );
	assert.equal( grid.hidden, true );
	assert.deepEqual( seatValues( doc, 'men' ), [ '1', '1', '1', '1', '1', '1' ] );
	assert.deepEqual( seatValues( doc, 'women' ), [ '5', '5', '5', '5', '5', '5' ] );
} );

test( 'negative shared values are clamped to zero when synced', () => {
	const { dom, doc } = loadSeats();
	const sameWomen = doc.getElementById( 'bmm_same_women' );
	sameWomen.value = '-3';
	sameWomen.dispatchEvent( new dom.window.Event( 'input' ) );

	assert.deepEqual( seatValues( doc, 'women' ), [ '0', '0', '0', '0', '0', '0' ] );
} );
