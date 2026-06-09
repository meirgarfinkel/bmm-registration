/**
 * BMM Registration — Nedarim Plus iframe lifecycle and postMessage handling.
 *
 * Follows the Nedarim Plus iframe integration spec:
 * https://www.matara.pro/nedarimplus/iframe/sample2.html
 *
 * Flow:
 * 1. Listen for bmm:ready-for-payment event (fired by bmm-form.js after server submission).
 * 2. Load the Nedarim Plus iframe.
 * 3. Respond to iframe resize requests.
 * 4. Send payment data to iframe via PostNedarim().
 * 5. Handle payment result from iframe.
 */
( function () {
	'use strict';

	const NEDARIM_IFRAME_URL = 'https://www.matara.pro/nedarimplus/iframe/';
	const NEDARIM_ORIGIN     = 'https://www.matara.pro';

	let iframe   = null;
	let nedarimData = null;
	let retryMode   = false;

	// ── Boot ──────────────────────────────────────────────────────────────────

	function init() {
		window.addEventListener( 'bmm:ready-for-payment', onReadyForPayment );
		window.addEventListener( 'message', onMessage );

		// Wire retry button (if it ever appears)
		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.id === 'bmm-retry-payment' ) {
				retryMode = true;
				loadIframe( nedarimData );
			}
		} );
	}

	// ── Payment init ──────────────────────────────────────────────────────────

	function onReadyForPayment( event ) {
		nedarimData = event.detail;
		loadIframe( nedarimData );
	}

	function loadIframe( data ) {
		const wrap = document.getElementById( 'bmm-iframe-wrap' );
		if ( ! wrap ) return;

		// Construct iframe URL — for HK (token creation mode), hide Tokef + CVV
		let iframeSrc = NEDARIM_IFRAME_URL;
		if ( data.payment_type === 'HK' ) {
			iframeSrc += '?Tokef=Hide&CVV=Hide';
		}

		iframe = document.getElementById( 'bmm-nedarim-iframe' );
		iframe.src = iframeSrc;

		wrap.hidden = false;
		setStatus( window.bmmConfig?.i18n?.processing || 'Processing payment...' );
	}

	// ── postMessage handler ───────────────────────────────────────────────────

	function onMessage( event ) {
		// Only accept messages from Nedarim Plus
		if ( event.origin !== NEDARIM_ORIGIN ) return;

		const data = event.data;

		// Iframe requests the available container height
		if ( data === 'NeedHeight' || ( typeof data === 'string' && data.startsWith( 'NeedHeight' ) ) ) {
			respondWithHeight();
			return;
		}

		// Iframe reports its own content height (number or digit string) → resize it
		if ( typeof data === 'number' || ( typeof data === 'string' && /^\d+$/.test( data ) ) ) {
			setIframeHeight( parseInt( data, 10 ) );
			return;
		}

		// Iframe is ready to receive payment data.
		// Nedarim Plus sends 'NedarimPlusReady' (their official name); some older
		// builds send 'NedarimReady' or { Action: 'NedarimPlusReady' }.
		if (
			data === 'NedarimPlusReady' ||
			data === 'NedarimReady' ||
			( typeof data === 'object' && data !== null &&
			  ( data.Action === 'NedarimPlusReady' || data.Action === 'NedarimReady' ) )
		) {
			sendPaymentData();
			return;
		}

		// Payment result object
		if ( typeof data === 'object' && data !== null && Object.prototype.hasOwnProperty.call( data, 'Status' ) ) {
			handleResult( data );
			return;
		}
	}

	/**
	 * Respond to Nedarim's NeedHeight request with the iframe wrapper's available height.
	 * Nedarim uses this to know how much vertical space it can expand into.
	 */
	function respondWithHeight() {
		if ( ! iframe || ! iframe.contentWindow ) return;
		const wrap   = document.getElementById( 'bmm-iframe-wrap' );
		const height = wrap ? wrap.clientHeight : document.documentElement.clientHeight;
		iframe.contentWindow.postMessage( height || 600, NEDARIM_ORIGIN );
	}

	function setIframeHeight( px ) {
		if ( iframe ) iframe.style.height = px + 'px';
	}

	// ── Send payment data to iframe ───────────────────────────────────────────

	function sendPaymentData() {
		if ( ! iframe || ! nedarimData ) return;

		const d    = nedarimData;
		const fCfg = window.bmmConfig || {};
		const state = window.bmmState || {};

		const fd = state.formData || {};

		const payload = {
			Mosad:       d.mosad,
			ApiValid:    d.api_valid,
			FirstName:   fd.first_name  || '',
			LastName:    fd.last_name   || '',
			Phone:       ( fd.phone     || '' ).replace( /\D/g, '' ),
			Mail:        fd.email       || '',
			City:        fd.city        || '',
			Street:      fd.address     || '',
			Zeout:       fd.zeout       || '',
			PaymentType: d.payment_type || 'Ragil',
			Amount:      String( d.total ),          // authoritative server total
			Tashlumim:   String( d.tashlumim || 1 ),
			Currency:    '1',                        // NIS
			Comment:     d.comment      || '',
			Param1:      String( d.submission_id ),
			CallBack:    d.callback_url || '',
		};

		// Use the PostNedarim function as specified in Nedarim Plus documentation
		iframe.contentWindow.postMessage( payload, NEDARIM_ORIGIN );
	}

	// ── Result handling ────────────────────────────────────────────────────────

	function handleResult( result ) {
		const wrap      = document.getElementById( 'bmm-iframe-wrap' );
		const successEl = document.getElementById( 'bmm-payment-success' );

		if ( result.Status === 'OK' || result.Status === 'Success' || result.IsCompleted === '1' ) {
			// Success
			if ( wrap )      wrap.hidden = true;
			if ( successEl ) {
				successEl.hidden = false;
				const confEl = document.getElementById( 'bmm-confirmation-number' );
				if ( confEl ) confEl.textContent = result.Confirmation || result.KevaId || result.TransactionId || '';
			}
			clearStatus();

			// Clear sessionStorage so a refresh doesn't re-show old data
			try {
				sessionStorage.removeItem( 'bmmFormData_' + ( window.bmmConfig?.formId || '' ) );
			} catch ( e ) {}

		} else {
			// Failure
			const errMsg = result.Message || result.Error || window.bmmConfig?.i18n?.paymentError || 'Payment failed.';
			setStatus( errMsg + ' <button id="bmm-retry-payment" class="bmm-btn bmm-btn--secondary bmm-btn--sm">Try Again</button>' );
		}
	}

	// ── Status ────────────────────────────────────────────────────────────────

	function setStatus( html ) {
		const el = document.getElementById( 'bmm-payment-status' );
		const msg = document.getElementById( 'bmm-payment-status-msg' );
		if ( el )  el.hidden = false;
		if ( msg ) msg.innerHTML = html;
	}

	function clearStatus() {
		const el = document.getElementById( 'bmm-payment-status' );
		if ( el ) el.hidden = true;
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
