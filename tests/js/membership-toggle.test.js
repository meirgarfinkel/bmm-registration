'use strict';

/**
 * Tests for the three-way mutual exclusivity between the Step 3 seat modes in
 * assets/js/bmm-form.js: Membership, Horaat Keva (already pays separately), and
 * Guest Seats. Checking any one must clear the other two, and collectStep(3)
 * must record the Horaat Keva flag.
 */

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { loadForm } = require( './_support.js' );

function check( dom, doc, id ) {
	const box = doc.getElementById( id );
	box.checked = true;
	box.dispatchEvent( new dom.window.Event( 'change' ) );
	return box;
}

test( 'checking Horaat Keva clears Membership and Guest Seats', () => {
	const { dom, doc } = loadForm();
	const membership = doc.getElementById( 'bmm_wants_membership' );
	const guest      = doc.getElementById( 'bmm_wants_guest_seats' );

	membership.checked = true;
	check( dom, doc, 'bmm_has_horaat_keva' );

	assert.equal( membership.checked, false, 'membership must clear' );
	assert.equal( guest.checked, false, 'guest seats must stay clear' );
	assert.equal( doc.getElementById( 'bmm_has_horaat_keva' ).checked, true );
} );

test( 'checking Membership clears Horaat Keva', () => {
	const { dom, doc } = loadForm();
	const horaatKeva = check( dom, doc, 'bmm_has_horaat_keva' );

	check( dom, doc, 'bmm_wants_membership' );

	assert.equal( horaatKeva.checked, false, 'Horaat Keva must clear' );
	assert.equal( doc.getElementById( 'bmm_wants_membership' ).checked, true );
} );

test( 'checking Guest Seats clears Horaat Keva', () => {
	const { dom, doc } = loadForm();
	const horaatKeva = check( dom, doc, 'bmm_has_horaat_keva' );

	check( dom, doc, 'bmm_wants_guest_seats' );

	assert.equal( horaatKeva.checked, false, 'Horaat Keva must clear' );
	assert.equal( doc.getElementById( 'bmm_wants_guest_seats' ).checked, true );
} );

test( 'collectStep(3) records the Horaat Keva flag', () => {
	const { dom, mod, state, doc } = loadForm();
	check( dom, doc, 'bmm_has_horaat_keva' );

	mod.collectStep( 3 );

	assert.equal( state.formData.has_horaat_keva, 1 );
	assert.equal( state.formData.wants_membership, 0 );
	assert.equal( state.formData.wants_guest_seats, 0 );
} );
