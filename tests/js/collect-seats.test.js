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
const path = require( 'node:path' );
const { JSDOM } = require( 'jsdom' );

const DAVENINGS = [ 'rh_night1', 'rh_day1', 'rh_night2', 'rh_day2', 'yk_night', 'yk_day' ];
const FORM_JS = path.resolve( __dirname, '../../assets/js/bmm-form.js' );

/**
 * Build a minimal registration DOM, load bmm-form.js into it (which runs its
 * init() and wires `wrap`), and return the exported collectors plus live state.
 */
function loadForm() {
	const seatRows = DAVENINGS.map( ( key ) => `
		<div class="bmm-seat-row" data-davening="${ key }">
			<input type="number" name="seats_men[${ key }]" class="bmm-seat-input bmm-seat-men" value="0" />
			<input type="number" name="seats_women[${ key }]" class="bmm-seat-input bmm-seat-women" value="0" />
		</div>` ).join( '' );

	const dom = new JSDOM( `<!DOCTYPE html><html><body>
		<div id="bmm-registration" data-form-id="1">
			<div id="bmm-error" hidden></div>
			<div class="bmm-form-step" data-step="3">
				<input type="checkbox" id="bmm_wants_membership" value="1" />
				<input type="checkbox" id="bmm_wants_guest_seats" value="1" />
				${ seatRows }
			</div>
			<button id="bmm-prev" type="button"></button>
			<button id="bmm-next" type="button"></button>
			<button id="bmm-submit-btn" type="button"></button>
		</div>
	</body></html>`, { url: 'http://localhost/', runScripts: 'outside-only' } );

	// bmm-form.js reads bare `window`/`document` globals.
	global.window = dom.window;
	global.document = dom.window.document;
	dom.window.bmmConfig = { formId: 1, davenings: DAVENINGS };

	delete require.cache[ FORM_JS ];
	const mod = require( FORM_JS );
	// Run init() explicitly so `wrap` is bound regardless of jsdom's
	// document.readyState timing (the auto-boot may defer to DOMContentLoaded).
	mod.init();

	return { dom, mod, state: dom.window.bmmState };
}

function setSeat( dom, gender, key, value ) {
	dom.window.document.querySelector( `[name="seats_${ gender }[${ key }]"]` ).value = String( value );
}

test( 'collectSeats reads the requested gender, not a substring match', () => {
	const { dom, mod } = loadForm();
	setSeat( dom, 'men', 'rh_day1', 1 );
	setSeat( dom, 'women', 'yk_day', 5 );

	const men = mod.collectSeats( 'men' );
	const women = mod.collectSeats( 'women' );

	assert.equal( men.rh_day1, 1 );
	assert.equal( men.yk_day, 0 );
	assert.equal( women.yk_day, 5, 'women collection must read the women inputs' );
	assert.equal( women.rh_day1, 0 );
} );

test( 'collectStep(3) records men and women seats independently (the reported bug)', () => {
	const { dom, mod, state } = loadForm();
	// Reproduce the report: 1 extra man, 5 extra women.
	setSeat( dom, 'men', 'rh_day1', 1 );
	setSeat( dom, 'women', 'yk_day', 5 );

	mod.collectStep( 3 );

	assert.equal( state.formData.seats_men.rh_day1, 1 );
	assert.equal(
		state.formData.seats_women.yk_day, 5,
		'women seats must not be overwritten by men seats'
	);
	// The max the server uses for pricing must differ between genders here.
	assert.notEqual(
		Math.max( ...Object.values( state.formData.seats_women ) ),
		Math.max( ...Object.values( state.formData.seats_men ) )
	);
} );

test( 'collectSeats clamps negatives and non-numbers to zero', () => {
	const { dom, mod } = loadForm();
	setSeat( dom, 'women', 'rh_night1', -4 );
	dom.window.document.querySelector( '[name="seats_women[rh_day1]"]' ).value = 'abc';

	const women = mod.collectSeats( 'women' );
	assert.equal( women.rh_night1, 0 );
	assert.equal( women.rh_day1, 0 );
} );
