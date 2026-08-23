'use strict';

/**
 * Shared jsdom fixtures for the front-end unit tests.
 *
 * Not a test file (the runner only picks up *.test.js), so it holds the DOM
 * builder and module loaders the individual suites reuse.
 */

const path = require( 'node:path' );
const { JSDOM } = require( 'jsdom' );

const DAVENINGS = [ 'rh_night1', 'rh_day1', 'rh_night2', 'rh_day2', 'yk_night', 'yk_day' ];

const DEFAULT_SPONSORSHIPS = [
	{ id: 'kiddush', label: 'Kiddush Fund', amount: 350, enabled: true },
	{ id: 'avos_ubanim', label: 'Avos Ubanim Fund', amount: 300, enabled: true },
	{ id: 'shalosh_seudos', label: 'Shalosh Seudos Fund', amount: 250, enabled: true },
];

function seatGridHtml() {
	const rows = DAVENINGS.map( ( key ) => `
		<div class="bmm-seat-row" data-davening="${ key }">
			<input type="number" name="seats_men[${ key }]" class="bmm-seat-input bmm-seat-men" value="0" />
			<input type="number" name="seats_women[${ key }]" class="bmm-seat-input bmm-seat-women" value="0" />
		</div>` ).join( '' );

	return `
		<div class="bmm-seats-same" id="bmm-seats-same-row" hidden>
			<input type="number" id="bmm_same_men" value="0" />
			<input type="number" id="bmm_same_women" value="0" />
		</div>
		<div class="bmm-seats-grid" id="bmm-seats-grid">${ rows }</div>`;
}

/** Full registration DOM covering the elements every front-end module touches. */
function registrationHtml() {
	return `<!DOCTYPE html><html><body>
		<div id="bmm-registration" data-form-id="1">
			<div id="bmm-error" hidden></div>
			<label class="bmm-toggle"><input type="checkbox" id="bmm_same_for_all" /></label>

			<div class="bmm-form-step" data-step="3">
				<input type="checkbox" id="bmm_wants_membership" value="1" />
				<input type="checkbox" id="bmm_wants_guest_seats" value="1" />
				${ seatGridHtml() }
			</div>

			<div class="bmm-form-step" data-step="4">
				<div class="bmm-sponsorships" id="bmm-sponsorships-list"></div>
				<div class="bmm-sponsorships bmm-sponsorship-other">
					<div class="bmm-sponsorship-other-field" id="bmm-sponsorship-other-field">
						<input type="number" id="bmm-sponsorship-other-amount" name="sponsorship_other" value="" />
					</div>
				</div>
			</div>

			<button id="bmm-prev" type="button"></button>
			<button id="bmm-next" type="button"></button>
			<button id="bmm-submit-btn" type="button"></button>
		</div>
	</body></html>`;
}

function makeDom( config = {} ) {
	const dom = new JSDOM( registrationHtml(), { url: 'http://localhost/' } );
	const win = dom.window;

	// The plugin scripts read bare `window`/`document`/`Event` globals.
	global.window = win;
	global.document = win.document;
	global.Event = win.Event;
	global.CustomEvent = win.CustomEvent;
	global.sessionStorage = win.sessionStorage;

	win.bmmConfig = Object.assign(
		{ formId: 1, davenings: DAVENINGS, sponsorships: DEFAULT_SPONSORSHIPS },
		config
	);
	return dom;
}

function loadModule( relPath ) {
	const abs = path.resolve( __dirname, '../../', relPath );
	delete require.cache[ abs ];
	return require( abs );
}

/** Load bmm-form.js against a fresh DOM and run its init(). */
function loadForm( config ) {
	const dom = makeDom( config );
	const mod = loadModule( 'assets/js/bmm-form.js' );
	mod.init();
	return { dom, mod, state: dom.window.bmmState, doc: dom.window.document };
}

/** Load bmm-seats.js against a fresh DOM and run its init(). */
function loadSeats( config ) {
	const dom = makeDom( config );
	const mod = loadModule( 'assets/js/bmm-seats.js' );
	mod.init();
	return { dom, mod, doc: dom.window.document };
}

module.exports = { DAVENINGS, DEFAULT_SPONSORSHIPS, makeDom, loadForm, loadSeats };
