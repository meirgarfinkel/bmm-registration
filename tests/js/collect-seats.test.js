'use strict';

/**
 * Regression tests for the seat-collection logic in assets/js/bmm-form.js.
 *
 * These guard the men/women mix-up: collectSeats() must read the inputs for
 * the gender it is asked for. The original bug derived the gender with
 * cls.includes('men') — and because the string "women" contains "men", the
 * women's collection read the men's inputs, undercharging the submission.
 */

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { loadForm } = require( './_support.js' );

function setSeat( doc, gender, key, value ) {
	doc.querySelector( `[name="seats_${ gender }[${ key }]"]` ).value = String( value );
}

test( 'collectSeats reads the requested gender, not a substring match', () => {
	const { mod, doc } = loadForm();
	setSeat( doc, 'men', 'rh_day1', 1 );
	setSeat( doc, 'women', 'yk_day', 5 );

	const men = mod.collectSeats( 'men' );
	const women = mod.collectSeats( 'women' );

	assert.equal( men.rh_day1, 1 );
	assert.equal( men.yk_day, 0 );
	assert.equal( women.yk_day, 5, 'women collection must read the women inputs' );
	assert.equal( women.rh_day1, 0 );
} );

test( 'collectStep(3) records men and women seats independently (the reported bug)', () => {
	const { mod, state, doc } = loadForm();
	// Reproduce the report: 1 extra man, 5 extra women.
	setSeat( doc, 'men', 'rh_day1', 1 );
	setSeat( doc, 'women', 'yk_day', 5 );

	mod.collectStep( 3 );

	assert.equal( state.formData.seats_men.rh_day1, 1 );
	assert.equal(
		state.formData.seats_women.yk_day, 5,
		'women seats must not be overwritten by men seats'
	);
	assert.notEqual(
		Math.max( ...Object.values( state.formData.seats_women ) ),
		Math.max( ...Object.values( state.formData.seats_men ) )
	);
} );

test( 'collectSeats clamps negatives and non-numbers to zero', () => {
	const { mod, doc } = loadForm();
	setSeat( doc, 'women', 'rh_night1', -4 );
	doc.querySelector( '[name="seats_women[rh_day1]"]' ).value = 'abc';

	const women = mod.collectSeats( 'women' );
	assert.equal( women.rh_night1, 0 );
	assert.equal( women.rh_day1, 0 );
} );
