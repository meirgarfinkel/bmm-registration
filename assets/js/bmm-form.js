/**
 * BMM Registration — multi-step form wizard.
 * Manages step state, navigation, validation, and form submission to the REST API.
 */
( function () {
	'use strict';

	const cfg = window.bmmConfig || {};

	// Global form state — shared with bmm-seats.js and bmm-pricing.js
	window.bmmState = {
		step:            1,
		totalSteps:      6,
		submissionId:    null,
		submittedTotal:  null,
		nedarimData:     null,  // set by bmm-rest-submit response
		formData:        {},    // accumulated across all steps
	};

	const state = window.bmmState;

	// ── DOM refs ──────────────────────────────────────────────────────────────
	let wrap, steps, stepEls, prevBtn, nextBtn, submitBtn, errorEl;

	function init() {
		wrap      = document.getElementById( 'bmm-registration' );
		if ( ! wrap ) return;

		stepEls   = wrap.querySelectorAll( '.bmm-form-step' );
		prevBtn   = document.getElementById( 'bmm-prev' );
		nextBtn   = document.getElementById( 'bmm-next' );
		submitBtn = document.getElementById( 'bmm-submit-btn' );
		errorEl   = document.getElementById( 'bmm-error' );

		prevBtn.addEventListener( 'click', goBack );
		nextBtn.addEventListener( 'click', goNext );
		submitBtn.addEventListener( 'click', submitToServer );

		// Add-child button
		const addChildBtn = document.getElementById( 'bmm-add-child' );
		if ( addChildBtn ) addChildBtn.addEventListener( 'click', addChildRow );

		// Populate sponsorships list (step 4)
		populateSponsorships();

		// Populate membership price hint
		populateMembershipHint();

		// Restore from sessionStorage
		restoreState();

		// Persist on any change
		wrap.addEventListener( 'change', persistState );
		wrap.addEventListener( 'input',  persistState );

		showStep( state.step );
	}

	// ── Step navigation ───────────────────────────────────────────────────────

	function showStep( n ) {
		state.step = n;

		stepEls.forEach( el => {
			const s = parseInt( el.dataset.step, 10 );
			el.hidden = s !== n;
		} );

		// Update step indicator
		wrap.querySelectorAll( '.bmm-step' ).forEach( el => {
			const s = parseInt( el.dataset.step, 10 );
			el.classList.toggle( 'bmm-step--active',    s === n );
			el.classList.toggle( 'bmm-step--completed', s < n );
		} );

		prevBtn.hidden   = n === 1;
		nextBtn.hidden   = n >= state.totalSteps;
		submitBtn.hidden = n !== state.totalSteps - 1; // step 5

		// When entering step 5, refresh pricing so the order summary is current
		if ( n === 5 && typeof window.bmmFetchPrice === 'function' ) {
			window.bmmFetchPrice();
		}

		// When entering step 6, trigger summary build
		if ( n === state.totalSteps ) {
			nextBtn.hidden   = true;
			submitBtn.hidden = true;
			if ( typeof window.bmmBuildSummary === 'function' ) {
				window.bmmBuildSummary();
			}
		}

		clearError();
	}

	function goNext() {
		const errors = validateStep( state.step );
		if ( errors.length ) {
			showError( errors[0] );
			return;
		}
		collectStep( state.step );
		showStep( state.step + 1 );
	}

	function goBack() {
		if ( state.step > 1 ) showStep( state.step - 1 );
	}

	// ── Validation ────────────────────────────────────────────────────────────

	function validateStep( step ) {
		const errors = [];

		if ( step === 1 ) {
			const required = [ 'first_name', 'last_name', 'phone', 'email' ];
			for ( const name of required ) {
				const el = wrap.querySelector( `[name="${name}"]` );
				if ( ! el || ! el.value.trim() ) {
					errors.push( cfg.i18n?.required || 'This field is required.' );
					if ( el ) el.focus();
					break;
				}
			}
			const emailEl = wrap.querySelector( '[name="email"]' );
			if ( emailEl && emailEl.value && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( emailEl.value ) ) {
				errors.push( cfg.i18n?.invalidEmail || 'Please enter a valid email address.' );
			}
		}

		if ( step === 2 ) {
			const hebrewEl = wrap.querySelector( '[name="hebrew_name"]' );
			if ( ! hebrewEl || ! hebrewEl.value.trim() ) {
				errors.push( cfg.i18n?.required || 'Hebrew name is required.' );
			}
			const tribeEl = wrap.querySelector( '[name="tribe"]:checked' );
			if ( ! tribeEl ) {
				errors.push( 'Please select your tribe.' );
			}
		}

		return errors;
	}

	// ── State collection ──────────────────────────────────────────────────────

	function collectStep( step ) {
		if ( step === 1 ) {
			[ 'first_name', 'last_name', 'phone', 'email', 'city', 'address', 'zeout' ].forEach( name => {
				const el = wrap.querySelector( `[name="${name}"]` );
				if ( el ) state.formData[ name ] = el.value.trim();
			} );
		}

		if ( step === 2 ) {
			state.formData.hebrew_name      = ( wrap.querySelector( '[name="hebrew_name"]' )?.value || '' ).trim();
			state.formData.tribe            = wrap.querySelector( '[name="tribe"]:checked' )?.value || 'yisrael';
			state.formData.wife_hebrew_name = ( wrap.querySelector( '[name="wife_hebrew_name"]' )?.value || '' ).trim();
			state.formData.children_hebrew_names = Array.from(
				wrap.querySelectorAll( '[name="children_hebrew_names[]"]' )
			).map( el => el.value.trim() ).filter( Boolean );
		}

		if ( step === 3 ) {
			state.formData.wants_membership = wrap.querySelector( '#bmm_wants_membership' )?.checked ? 1 : 0;
			state.formData.seats_men        = collectSeats( 'bmm-seat-men' );
			state.formData.seats_women      = collectSeats( 'bmm-seat-women' );
		}

		if ( step === 4 ) {
			state.formData.sponsorship_ids = Array.from(
				wrap.querySelectorAll( '.bmm-sponsorship-check:checked' )
			).map( el => el.value );
		}

		if ( step === 5 ) {
			state.formData.notes        = ( wrap.querySelector( '[name="notes"]' )?.value || '' ).trim();
			state.formData.payment_type = wrap.querySelector( '[name="payment_type"]:checked' )?.value || 'Ragil';
		}
	}

	function collectSeats( cls ) {
		const seats = {};
		( cfg.davenings || [] ).forEach( key => {
			const el = wrap.querySelector( `[name="seats_${cls.includes('men') ? 'men' : 'women'}[${key}]"]` );
			seats[ key ] = el ? Math.max( 0, parseInt( el.value, 10 ) || 0 ) : 0;
		} );
		return seats;
	}

	// ── Server submission ──────────────────────────────────────────────────────

	async function submitToServer() {
		// Collect step 5 before submitting
		collectStep( 5 );

		submitBtn.disabled = true;
		submitBtn.textContent = cfg.i18n?.submitting || 'Saving registration...';
		clearError();

		const body = {
			form_id:   parseInt( wrap.dataset.formId, 10 ),
			_wpnonce:  cfg.nonce,
			...state.formData,
		};

		try {
			const res = await fetch( cfg.submitEndpoint, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   cfg.nonce,
				},
				body: JSON.stringify( body ),
			} );

			const json = await res.json();

			if ( ! res.ok ) {
				const msg = json.message || json.data?.message || 'Submission failed.';
				showError( msg );
				submitBtn.disabled = false;
				submitBtn.textContent = 'Proceed to Payment';
				return;
			}

			// Success — store submission data and advance to payment step
			state.submissionId   = json.submission_id;
			state.submittedTotal = json.total;
			state.nedarimData    = json;

			// Move to step 6 and fire the Nedarim iframe
			showStep( 6 );
			window.dispatchEvent( new CustomEvent( 'bmm:ready-for-payment', { detail: json } ) );

		} catch ( err ) {
			showError( 'Network error. Please try again.' );
			submitBtn.disabled = false;
			submitBtn.textContent = 'Proceed to Payment';
		}
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	function showError( msg ) {
		errorEl.textContent = msg;
		errorEl.hidden = false;
		errorEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	function clearError() {
		errorEl.textContent = '';
		errorEl.hidden = true;
	}

	function addChildRow() {
		const list = document.getElementById( 'bmm-children-list' );
		if ( ! list ) return;

		const row = document.createElement( 'div' );
		row.className = 'bmm-child-row';
		row.innerHTML = `<input type="text" name="children_hebrew_names[]" dir="rtl" placeholder="${ cfg.i18n?.childPlaceholder || 'Child Hebrew name' }" />
			<button type="button" class="bmm-btn-icon bmm-remove-child" aria-label="Remove">&times;</button>`;

		row.querySelector( '.bmm-remove-child' ).addEventListener( 'click', () => {
			row.remove();
			updateRemoveButtons();
		} );

		list.appendChild( row );
		updateRemoveButtons();
		row.querySelector( 'input' ).focus();
	}

	function updateRemoveButtons() {
		const rows   = document.querySelectorAll( '.bmm-child-row' );
		const remove = document.querySelectorAll( '.bmm-remove-child' );
		remove.forEach( btn => { btn.hidden = rows.length <= 1; } );
	}

	function populateSponsorships() {
		const container = document.getElementById( 'bmm-sponsorships-list' );
		if ( ! container || ! cfg.sponsorships ) return;

		cfg.sponsorships.forEach( s => {
			const label = document.createElement( 'label' );
			label.className = 'bmm-checkbox bmm-sponsorship-option';
			label.innerHTML = `
				<input type="checkbox" class="bmm-sponsorship-check" value="${ escAttr( s.id ) }" />
				<span>
					<strong>${ escHtml( s.label ) }</strong>
					<span class="bmm-sponsorship-amount">₪${ escHtml( String( s.amount ) ) }</span>
				</span>`;
			container.appendChild( label );
		} );
	}

	function populateMembershipHint() {
		const priceEl = document.getElementById( 'bmm-membership-price' );
		if ( priceEl ) priceEl.textContent = cfg.membershipPrice || 0;

		const includesEl = document.getElementById( 'bmm-membership-includes-text' );
		if ( includesEl ) {
			const men   = cfg.includedMen   || 0;
			const women = cfg.includedWomen || 0;
			if ( men > 0 || women > 0 ) {
				const parts = [];
				if ( men   > 0 ) parts.push( `${ men } men's seat${ men   !== 1 ? 's' : '' }` );
				if ( women > 0 ) parts.push( `${ women } women's seat${ women !== 1 ? 's' : '' }` );
				includesEl.textContent = `Includes ${ parts.join( ' and ' ) } for all davenings.`;
			} else {
				includesEl.textContent = '';
			}
		}
	}

	// ── Session storage persistence ───────────────────────────────────────────

	function persistState() {
		try {
			sessionStorage.setItem( 'bmmFormData_' + cfg.formId, JSON.stringify( state.formData ) );
		} catch ( e ) {}
	}

	function restoreState() {
		try {
			const saved = sessionStorage.getItem( 'bmmFormData_' + cfg.formId );
			if ( ! saved ) return;
			const data = JSON.parse( saved );
			Object.assign( state.formData, data );
			// Re-populate fields
			Object.entries( data ).forEach( ( [ name, value ] ) => {
				const el = wrap.querySelector( `[name="${ name }"]` );
				if ( el && typeof value === 'string' ) el.value = value;
			} );
		} catch ( e ) {}
	}

	// ── String escaping helpers ───────────────────────────────────────────────

	function escHtml( s ) {
		return String( s ).replace( /&/g,'&amp;').replace( /</g,'&lt;').replace( />/g,'&gt;').replace( /"/g,'&quot;');
	}
	function escAttr( s ) { return escHtml( s ); }

	// ── Summary builder (called from step 6) ─────────────────────────────────

	window.bmmBuildSummary = function () {
		const rows = document.getElementById( 'bmm-summary-rows' );
		const totalEl = document.getElementById( 'bmm-summary-total' );
		if ( ! rows || ! state.nedarimData ) return;

		rows.innerHTML = '';
		const d = state.nedarimData.itemized || {};

		function addRow( label, amount ) {
			if ( ! amount ) return;
			const tr = document.createElement( 'tr' );
			tr.innerHTML = `<td>${ escHtml( label ) }</td><td>₪${ escHtml( String( amount ) ) }</td>`;
			rows.appendChild( tr );
		}

		if ( d.membership )       addRow( 'Membership', d.membership );
		if ( d.extra_men_seats )  addRow( `Extra men's seats (×${ d.extra_men_count })`, d.extra_men_seats );
		if ( d.extra_women_seats) addRow( `Extra women's seats (×${ d.extra_women_count })`, d.extra_women_seats );
		( d.sponsorships || [] ).forEach( s => addRow( s.label, s.amount ) );

		if ( totalEl ) totalEl.textContent = '₪' + ( state.submittedTotal || 0 );
	};

	// ── Boot ──────────────────────────────────────────────────────────────────

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
