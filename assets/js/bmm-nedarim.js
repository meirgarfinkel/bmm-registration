/**
 * BMM Registration — Nedarim Plus iframe lifecycle and postMessage handling.
 *
 * Implements the Nedarim Plus iframe protocol (matara.pro):
 *   - Parent → iframe:  postMessage( { Name, Value }, target )  via PostNedarim()
 *   - iframe → parent:  { Name: 'Height',  Value: <px> }            → resize iframe
 *                       { Name: 'TransactionResponse', Value: {…} } → payment result
 *
 * Flow:
 *   1. bmm-form.js fires `bmm:ready-for-payment` after the server submission.
 *   2. We load the iframe and, on its `load` event, send the transaction
 *      parameters via PostNedarim('FinishTransaction2', {...}). This renders
 *      the full payment form (with the locked amount).
 *   3. The customer enters card details and pays inside the iframe.
 *   4. The iframe posts back its Height (we resize) and finally a
 *      TransactionResponse (we show success / error).
 */
( function () {
	'use strict';

	// Load marker for the admin diagnostic.
	window.bmmNedarimLoaded = true;

	const FRAME_ID = 'bmm-nedarim-iframe';
	const BASE_URL = 'https://www.matara.pro/nedarimplus/iframe/';
	const TARGET   = 'https://www.matara.pro';

	let nedarimData = null;
	let dataSent    = false;

	function frame() {
		return document.getElementById( FRAME_ID );
	}

	/** Send a message to the Nedarim iframe in its expected { Name, Value } shape. */
	function postNedarim( name, value ) {
		const f = frame();
		if ( f && f.contentWindow ) {
			f.contentWindow.postMessage( { Name: name, Value: value }, TARGET );
		}
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	function init() {
		window.addEventListener( 'bmm:ready-for-payment', onReadyForPayment );
		window.addEventListener( 'message', onMessage, false );

		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.id === 'bmm-retry-payment' ) {
				loadIframe( nedarimData );
			}
		} );
	}

	function onReadyForPayment( event ) {
		nedarimData = event.detail;
		loadIframe( nedarimData );
	}

	function loadIframe( data ) {
		const wrap = document.getElementById( 'bmm-iframe-wrap' );
		const f    = frame();
		if ( ! wrap || ! f ) return;

		// HK (standing order) hides validity + CVV per Nedarim's token rules.
		let src = BASE_URL;
		if ( data && data.payment_type === 'HK' ) {
			src += '?Tokef=Hide&CVV=Hide';
		}

		dataSent = false;
		// Send the transaction parameters once the iframe document has loaded.
		f.onload = function () { sendPaymentData(); };
		f.src = src;

		wrap.hidden = false;
		setStatus( window.bmmConfig && window.bmmConfig.i18n && window.bmmConfig.i18n.processing
			? window.bmmConfig.i18n.processing
			: 'Loading secure payment form…' );
	}

	// ── postMessage handler ─────────────────────────────────────────────────────

	function onMessage( event ) {
		// Only accept messages from the Nedarim domain (www / non-www).
		if ( typeof event.origin === 'string' && event.origin.indexOf( 'matara.pro' ) === -1 ) {
			return;
		}
		const d = event.data;
		if ( ! d || typeof d !== 'object' ) {
			return;
		}

		switch ( d.Name ) {
			case 'Height':
				setIframeHeight( parseInt( d.Value, 10 ) );
				// First Height message proves the iframe's JS is running; make
				// sure it has received the transaction parameters.
				if ( ! dataSent ) {
					sendPaymentData();
				}
				break;

			case 'TransactionResponse':
				handleResult( d.Value || {} );
				break;
		}
	}

	function setIframeHeight( px ) {
		const f = frame();
		if ( f && px > 0 ) {
			f.style.height = ( px + 30 ) + 'px';
		}
	}

	// ── Send payment data to iframe ─────────────────────────────────────────────

	function sendPaymentData() {
		if ( dataSent || ! nedarimData ) {
			return;
		}
		const d  = nedarimData;
		const fd = ( window.bmmState && window.bmmState.formData ) || {};

		const isHK = d.payment_type === 'HK';
		const tashlumim = ( d.tashlumim && String( d.tashlumim ) !== '0' )
			? String( d.tashlumim )
			: ( isHK ? '' : '1' ); // HK blank = unlimited; Ragil default 1

		// All parameters must be present, even if empty (per Nedarim spec).
		postNedarim( 'FinishTransaction2', {
			Mosad:            String( d.mosad || '' ),
			ApiValid:         String( d.api_valid || '' ),
			Zeout:            fd.zeout       || '',
			FirstName:        fd.first_name  || '',
			LastName:         fd.last_name   || '',
			Street:           fd.address     || '',
			City:             fd.city        || '',
			Phone:            ( fd.phone || '' ).replace( /\D/g, '' ),
			Mail:             fd.email       || '',
			PaymentType:      isHK ? 'HK' : 'Ragil',
			Amount:           String( d.total ),   // authoritative, server-computed
			Tashlumim:        tashlumim,
			Day:              '',
			Currency:         '1',                  // 1 = NIS
			Groupe:           '',
			Comment:          d.comment      || '',
			Param1:           String( d.submission_id || '' ),
			Param2:           '',
			CallBack:         d.callback_url || '',
			CallBackMailError:'',
		} );

		dataSent = true;
	}

	// ── Result handling ──────────────────────────────────────────────────────────

	function handleResult( result ) {
		const wrap      = document.getElementById( 'bmm-iframe-wrap' );
		const successEl = document.getElementById( 'bmm-payment-success' );
		const status    = String( result.Status || '' ).toLowerCase();
		const ok        = status === 'ok' || status === 'success' || result.IsCompleted === '1';

		if ( ok ) {
			if ( wrap ) {
				wrap.hidden = true;
			}
			if ( successEl ) {
				successEl.hidden = false;
				const confEl = document.getElementById( 'bmm-confirmation-number' );
				if ( confEl ) {
					confEl.textContent = result.ConfirmationNumber || result.Confirmation
						|| result.KevaId || result.TransactionId || '';
				}
			}
			clearStatus();

			// Clear saved form data so a refresh doesn't re-show stale state.
			try {
				sessionStorage.removeItem( 'bmmFormData_' + ( window.bmmConfig && window.bmmConfig.formId ? window.bmmConfig.formId : '' ) );
			} catch ( e ) {}
		} else {
			const errMsg = result.Message || result.Error
				|| ( window.bmmConfig && window.bmmConfig.i18n && window.bmmConfig.i18n.paymentError )
				|| 'Payment failed.';
			// Allow another attempt — reset so the params re-send on retry.
			dataSent = false;
			setStatus( errMsg + ' <button id="bmm-retry-payment" class="bmm-btn bmm-btn--secondary bmm-btn--sm">Try Again</button>' );
		}
	}

	// ── Status ────────────────────────────────────────────────────────────────

	function setStatus( html ) {
		const el  = document.getElementById( 'bmm-payment-status' );
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
