'use strict';

/**
 * Tests for sponsorship + Kiddush Fund collection in collectStep(4).
 *
 * Sponsorships are rendered from bmmConfig.sponsorships by populateSponsorships();
 * the Kiddush Fund carries a date + dedication that must only be captured while
 * Kiddush is selected.
 */

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { loadForm } = require( './_support.js' );

function check( doc, id ) {
	doc.querySelector( `.bmm-sponsorship-check[value="${ id }"]` ).checked = true;
}

test( 'selected sponsorship ids are collected in DOM order', () => {
	const { mod, state, doc } = loadForm();
	check( doc, 'kiddush' );
	check( doc, 'shalosh_seudos' );

	mod.collectStep( 4 );

	assert.deepEqual( state.formData.sponsorship_ids, [ 'kiddush', 'shalosh_seudos' ] );
} );

test( 'the free-form "Other" amount is collected and clamps negatives', () => {
	const { mod, state, doc } = loadForm();
	doc.getElementById( 'bmm-sponsorship-other-amount' ).value = '200';
	mod.collectStep( 4 );
	assert.equal( state.formData.sponsorship_other, 200 );

	doc.getElementById( 'bmm-sponsorship-other-amount' ).value = '-50';
	mod.collectStep( 4 );
	assert.equal( state.formData.sponsorship_other, 0 );
} );

test( 'Kiddush date + dedication are captured when Kiddush is selected', () => {
	const { mod, state, doc } = loadForm();
	check( doc, 'kiddush' );
	doc.querySelector( '[name="kiddush_date"]' ).value = '2026-09-20';
	doc.querySelector( '[name="kiddush_dedication"]' ).value = '  In memory of...  ';

	mod.collectStep( 4 );

	assert.equal( state.formData.kiddush_date, '2026-09-20' );
	assert.equal( state.formData.kiddush_dedication, 'In memory of...', 'dedication is trimmed' );
} );

test( 'Kiddush fields are dropped when Kiddush is NOT selected', () => {
	const { mod, state, doc } = loadForm();
	check( doc, 'avos_ubanim' ); // not kiddush
	// Stray values left in the (hidden) inputs must not leak into the submission.
	doc.querySelector( '[name="kiddush_date"]' ).value = '2026-01-01';
	doc.querySelector( '[name="kiddush_dedication"]' ).value = 'stale';

	mod.collectStep( 4 );

	assert.deepEqual( state.formData.sponsorship_ids, [ 'avos_ubanim' ] );
	assert.equal( state.formData.kiddush_date, '' );
	assert.equal( state.formData.kiddush_dedication, '' );
} );

test( 'the Kiddush fields appear only for the Kiddush option', () => {
	const { doc } = loadForm();
	// populateSponsorships() should have built exactly one Kiddush subfield block,
	// placed within the sponsorships list.
	const blocks = doc.querySelectorAll( '#bmm-kiddush-fields' );
	assert.equal( blocks.length, 1 );
	assert.ok( doc.querySelector( '[name="kiddush_date"]' ) );
	assert.ok( doc.querySelector( '[name="kiddush_dedication"]' ) );
} );

test( 'the Kiddush fields toggle visibility with the Kiddush checkbox', () => {
	const { dom, doc } = loadForm();
	const box = doc.querySelector( '.bmm-sponsorship-check[value="kiddush"]' );
	const fields = doc.getElementById( 'bmm-kiddush-fields' );

	assert.equal( fields.hidden, true, 'hidden until Kiddush is ticked' );

	box.checked = true;
	box.dispatchEvent( new dom.window.Event( 'change' ) );
	assert.equal( fields.hidden, false, 'shown once Kiddush is ticked' );

	box.checked = false;
	box.dispatchEvent( new dom.window.Event( 'change' ) );
	assert.equal( fields.hidden, true, 'hidden again once un-ticked' );
} );
