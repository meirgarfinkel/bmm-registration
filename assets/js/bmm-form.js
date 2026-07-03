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
	let submitting = false; // guards against double / re-entrant submits

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

		// Populate membership and guest seat price hints
		populateMembershipHint();
		populateGuestSeatPriceHint();

		// Enforce mutual exclusivity between membership and guest seats
		wireGuestMembershipToggle();

		// Update the installments selector when the payment type changes,
		// and the per-payment hint when the number of payments changes.
		wrap.querySelectorAll( '[name="payment_type"]' ).forEach( el => {
			el.addEventListener( 'change', updateTashlumimSelector );
		} );
		const tashSelect = document.getElementById( 'bmm_tashlumim' );
		if ( tashSelect ) tashSelect.addEventListener( 'change', updateTashlumimHint );

		// Restore from sessionStorage
		restoreState();

		// "Other" sponsorship: toggle the amount field and forbid negatives.
		// Runs after restoreState so a restored amount re-checks the toggle.
		wireOtherSponsorship();

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
		// Hide "Next" on step 5 AND 6. Step 5's only forward action is the
		// "Proceed to Payment" button, which submits and loads the payment
		// iframe. Previously "Next" also showed on step 5, so clicking it
		// skipped the submit and landed on an empty step 6 with no iframe.
		nextBtn.hidden   = n >= state.totalSteps - 1;
		submitBtn.hidden = n !== state.totalSteps - 1; // "Proceed to Payment" only on step 5

		// Refresh pricing whenever the user reaches step 3 or 5
		if ( ( n === 3 || n === 5 ) && typeof window.bmmFetchPrice === 'function' ) {
			window.bmmFetchPrice();
		}
		// Keep the installments/months selector in sync on step 5
		if ( n === 5 ) {
			updateTashlumimSelector();
		}

		// Step 6 (payment): a submission must exist for the iframe to load.
		// If we somehow arrive without one, submit now. The submissionId and
		// submitting guards prevent a double submit on the normal
		// "Proceed to Payment" → submitToServer → showStep(6) flow.
		if ( n === state.totalSteps ) {
			nextBtn.hidden   = true;
			submitBtn.hidden = true;
			if ( ! state.submissionId && ! submitting ) {
				submitToServer();
			} else if ( typeof window.bmmBuildSummary === 'function' ) {
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
			state.formData.wants_membership  = wrap.querySelector( '#bmm_wants_membership' )?.checked  ? 1 : 0;
			state.formData.wants_guest_seats = wrap.querySelector( '#bmm_wants_guest_seats' )?.checked ? 1 : 0;
			state.formData.seats_men         = collectSeats( 'bmm-seat-men' );
			state.formData.seats_women       = collectSeats( 'bmm-seat-women' );
		}

		if ( step === 4 ) {
			state.formData.sponsorship_ids = Array.from(
				wrap.querySelectorAll( '.bmm-sponsorship-check:checked' )
			).map( el => el.value );
			state.formData.sponsorship_other = readOtherSponsorshipAmount();
		}

		if ( step === 5 ) {
			state.formData.notes        = ( wrap.querySelector( '[name="notes"]' )?.value || '' ).trim();
			const type = wrap.querySelector( '[name="payment_type"]:checked' )?.value || 'Ragil';
			state.formData.payment_type = type;
			// Number of payments: 1 for "pay in full"; the chosen count for Tashlumim.
			const tashSel = document.getElementById( 'bmm_tashlumim' );
			state.formData.tashlumim = ( type === 'Tashlumim' && tashSel ) ? ( parseInt( tashSel.value, 10 ) || 2 ) : 1;
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
		if ( submitting || state.submissionId ) return; // never submit twice
		submitting = true;

		// Collect step 5 before submitting
		collectStep( 5 );

		submitBtn.disabled = true;
		submitBtn.textContent = cfg.i18n?.submitting || 'Saving registration...';
		clearError();

		const body = {
			form_id:   parseInt( wrap.dataset.formId, 10 ),
			...state.formData,
		};

		try {
			const res = await fetch( cfg.submitEndpoint, {
				method:  'POST',
				// No X-WP-Nonce header: this is a public, anonymous endpoint.
				// Any X-WP-Nonce value (valid or not) forces WordPress's REST
				// cookie-auth check, which 403s ("Cookie check failed") whenever
				// the nonce isn't a fresh 'wp_rest' nonce — which breaks under
				// page caching and for logged-out users. Omitting it entirely
				// makes WordPress skip that check.
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( body ),
			} );

			const json = await res.json();

			if ( ! res.ok ) {
				const msg = json.message || json.data?.message || 'Submission failed.';
				showError( msg );
				submitBtn.disabled = false;
				submitBtn.textContent = 'Proceed to Payment';
				submitting = false;
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
			submitting = false;
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

	// "Other amount" sponsorship: an always-visible free-form field where the
	// donor can enter any amount. Negative amounts are never accepted.
	function wireOtherSponsorship() {
		const input = document.getElementById( 'bmm-sponsorship-other-amount' );
		if ( ! input ) return;

		// No negatives — clamp on input.
		input.addEventListener( 'input', function () {
			const n = parseInt( input.value, 10 );
			if ( input.value !== '' && ( isNaN( n ) || n < 0 ) ) input.value = '0';
		} );
	}

	function readOtherSponsorshipAmount() {
		const input = document.getElementById( 'bmm-sponsorship-other-amount' );
		if ( ! input ) return 0;
		return Math.max( 0, parseInt( input.value, 10 ) || 0 );
	}

	function populateGuestSeatPriceHint() {
		const el = document.getElementById( 'bmm-guest-seat-price' );
		if ( el ) el.textContent = cfg.guestSeatPrice || 0;
	}

	// Payment method on step 5: "Pay in full" (Ragil) or "Pay in installments"
	// (Tashlumim, 2..maxInstallments). The Tashlumim radio + the count selector
	// only appear when the form allows installments (maxInstallments > 1).
	function updateTashlumimSelector() {
		const maxInst    = parseInt( cfg.maxInstallments, 10 ) || 1;
		const tashOption = document.getElementById( 'bmm-option-tashlumim' );
		const field      = document.getElementById( 'bmm-tashlumim-field' );
		const select     = document.getElementById( 'bmm_tashlumim' );

		// Offer the installments option only when the form allows it.
		if ( tashOption ) tashOption.hidden = maxInst <= 1;

		if ( maxInst <= 1 ) {
			const ragil = wrap.querySelector( '[name="payment_type"][value="Ragil"]' );
			if ( ragil ) ragil.checked = true;
			if ( field ) field.hidden = true;
			return;
		}

		const type = wrap.querySelector( '[name="payment_type"]:checked' )?.value || 'Ragil';
		if ( type !== 'Tashlumim' ) {
			if ( field ) field.hidden = true;
			return;
		}

		// Tashlumim chosen → populate the count selector with 2..maxInst.
		if ( select ) {
			const current = select.value;
			select.innerHTML = '';
			for ( let i = 2; i <= maxInst; i++ ) {
				const opt = document.createElement( 'option' );
				opt.value = String( i );
				opt.textContent = i + ' payments';
				select.appendChild( opt );
			}
			// Keep a previous valid choice; otherwise default to the maximum
			// (e.g. 12) so the customer spreads payments as far as allowed.
			if ( current && parseInt( current, 10 ) >= 2 && parseInt( current, 10 ) <= maxInst ) {
				select.value = current;
			} else {
				select.value = String( maxInst );
			}
		}
		if ( field ) field.hidden = false;
		updateTashlumimHint();
	}

	// "≈ ₪300 × 10 = ₪3,000 total" helper under the count selector.
	function updateTashlumimHint() {
		const hint   = document.getElementById( 'bmm-tashlumim-hint' );
		const select = document.getElementById( 'bmm_tashlumim' );
		if ( ! hint || ! select ) return;
		const total = ( window.bmmState && window.bmmState.lastPricing && window.bmmState.lastPricing.total ) || 0;
		const n     = parseInt( select.value, 10 ) || 0;
		hint.textContent = ( total && n ) ? ( '≈ ₪' + Math.round( total / n ) + ' × ' + n + ' months  (₪' + total + ' total)' ) : '';
	}

	function wireGuestMembershipToggle() {
		const membershipCb = document.getElementById( 'bmm_wants_membership' );
		const guestCb      = document.getElementById( 'bmm_wants_guest_seats' );
		if ( ! membershipCb || ! guestCb ) return;

		membershipCb.addEventListener( 'change', function () {
			if ( this.checked ) guestCb.checked = false;
			if ( typeof window.bmmFetchPrice === 'function' ) window.bmmFetchPrice();
		} );
		guestCb.addEventListener( 'change', function () {
			if ( this.checked ) membershipCb.checked = false;
			if ( typeof window.bmmFetchPrice === 'function' ) window.bmmFetchPrice();
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
			// Re-populate fields. Radios/checkboxes must be restored by *checking*
			// the input whose value matches — NOT by setting .value on the first
			// element of the group, which would corrupt its value (e.g. overwriting
			// the "Pay in full" radio's value with "Tashlumim" and making it submit
			// as installments).
			Object.entries( data ).forEach( ( [ name, value ] ) => {
				if ( typeof value !== 'string' ) return;
				const els = wrap.querySelectorAll( `[name="${ name }"]` );
				if ( ! els.length ) return;
				const first = els[ 0 ];
				if ( first.type === 'radio' || first.type === 'checkbox' ) {
					els.forEach( el => { el.checked = ( el.value === value ); } );
				} else {
					first.value = value;
				}
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
		if ( d.guest_men_seats )  addRow( `Guest men's seats (×${ d.guest_men_count })`, d.guest_men_seats );
		if ( d.guest_women_seats) addRow( `Guest women's seats (×${ d.guest_women_count })`, d.guest_women_seats );
		( d.sponsorships || [] ).forEach( s => addRow( s.label, s.amount ) );

		const total = state.submittedTotal || 0;
		if ( totalEl ) totalEl.textContent = '₪' + total;

		// Installment plan note: the total is charged in N monthly payments.
		const noteEl = document.getElementById( 'bmm-installment-note' );
		if ( noteEl ) {
			const n = parseInt( state.nedarimData.tashlumim, 10 ) || 1;
			if ( total && n > 1 ) {
				noteEl.textContent = `Charged in ${ n } monthly payments of ≈ ₪${ Math.round( total / n ) } (₪${ total } total).`;
				noteEl.hidden = false;
			} else {
				noteEl.textContent = '';
				noteEl.hidden = true;
			}
		}
	};

	// ── Boot ──────────────────────────────────────────────────────────────────

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
