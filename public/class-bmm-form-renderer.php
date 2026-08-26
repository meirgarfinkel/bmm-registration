<?php
defined( 'ABSPATH' ) || exit;

class BMM_Form_Renderer {

	public static function render( int $form_id ): string {
		try {
			$form = new BMM_Form_Config( $form_id );
		} catch ( \Exception $e ) {
			return '<p>' . esc_html__( 'Registration form not found.', 'bmm-registration' ) . '</p>';
		}

		// Archived: show closed message
		if ( $form->status === 'archived' ) {
			return '<div class="bmm-form-closed"><p>' . esc_html__( 'Registration for this form has closed.', 'bmm-registration' ) . '</p></div>';
		}

		// Draft: admins only — show a preview banner
		if ( $form->status === 'draft' ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return '<p>' . esc_html__( 'This registration form is not currently available.', 'bmm-registration' ) . '</p>';
			}
			add_filter( 'bmm_form_before_html', function (): string {
				return '<div class="bmm-draft-notice" style="background:#fcf8e3;border:1px solid #faebcc;color:#8a6d3b;padding:10px 14px;margin-bottom:16px;border-radius:3px;">'
					. esc_html__( 'Admin preview — this form is a draft and not yet visible to the public.', 'bmm-registration' )
					. '</div>';
			} );
		}

		if ( ! $form->mosad || ! $form->api_valid ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p style="color:red;">' . esc_html__( 'Admin: Nedarim Plus credentials are not configured. Please set them in BMM Registration → Settings or in the form editor.', 'bmm-registration' ) . '</p>';
			}
			return '<p>' . esc_html__( 'Payment configuration error. Please contact the administrator.', 'bmm-registration' ) . '</p>';
		}

		self::enqueue_assets( $form );
		return self::get_html( $form ) . self::admin_diagnostic( $form );
	}

	/**
	 * Admin-only on-page diagnostic. Surfaces whether the JS config/scripts
	 * loaded and what the live price REST endpoint actually returns, so the
	 * cause of "subtotals/payment not working" is unambiguous. Renders nothing
	 * for non-admins.
	 */
	private static function admin_diagnostic( BMM_Form_Config $form ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		$price_ep    = esc_url_raw( rest_url( 'bmm/v1/calculate-price' ) );
		$submit_ep   = esc_url_raw( rest_url( 'bmm/v1/submit' ) );
		$simulate_ep = esc_url_raw( rest_url( 'bmm/v1/simulate-callback' ) );
		$rest_nonce  = wp_create_nonce( 'wp_rest' );
		$fid         = (int) $form->post_id;

		ob_start();
		?>
<div id="bmm-admin-diagnostic" style="margin-top:24px;padding:12px 16px;border:1px dashed #b58105;background:#fffbe6;color:#5b4708;font:12px/1.6 monospace;white-space:pre-wrap;">BMM diagnostic (visible to admins only) — running…</div>
<div style="margin-top:8px;">
	<button type="button" id="bmm-simulate-callback" class="bmm-btn bmm-btn--secondary bmm-btn--sm">Simulate Nedarim callback (creates a TEST submission)</button>
	<div id="bmm-simulate-result" style="margin-top:8px;font:12px/1.6 monospace;white-space:pre-wrap;color:#5b4708;"></div>
</div>
<script>
( function () {
	window.addEventListener( 'load', function () {
		var box = document.getElementById( 'bmm-admin-diagnostic' );
		if ( ! box ) { return; }
		var out = [];
		out.push( 'plugin version: <?php echo esc_js( BMM_REG_VERSION ); ?>' );
		out.push( 'theme: <?php echo esc_js( wp_get_theme()->get( 'Name' ) ); ?> (block theme: <?php echo ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) ? 'YES' : 'no'; ?>)' );
		out.push( 'bmmConfig: ' + ( window.bmmConfig ? 'present' : 'MISSING' ) );
		out.push( 'bmm-form.js loaded: ' + ( typeof window.bmmState !== 'undefined' ) );
		out.push( 'bmm-pricing.js loaded: ' + ( typeof window.bmmFetchPrice === 'function' ) );
		out.push( 'bmm-nedarim.js loaded: ' + ( window.bmmNedarimLoaded === true ) );

		// Duplicate-render check: these MUST be 1. More than one means the form
		// was rendered twice, which breaks all JS via duplicate element IDs.
		out.push( '#bmm-registration count: ' + document.querySelectorAll( '#bmm-registration' ).length + ' (must be 1)' );
		out.push( 'wants_membership input count: ' + document.querySelectorAll( '[name="wants_membership"]' ).length + ' (must be 1)' );

		// DOM presence of the elements updateUI()/Nedarim target.
		function exists( id ) { return document.getElementById( id ) ? 'yes' : 'MISSING'; }
		out.push( 'subtotal row el: ' + exists( 'bmm-preview-subtotal' ) + ', amount el: ' + exists( 'bmm-preview-subtotal-amount' ) );
		out.push( 'guest checkbox el: ' + exists( 'bmm_wants_guest_seats' ) );
		out.push( 'nedarim iframe el: ' + exists( 'bmm-nedarim-iframe' ) + ', wrap el: ' + exists( 'bmm-iframe-wrap' ) );
		box.textContent = out.join( '\n' );

		// Drive the REAL updateUI path: tick membership, call the real
		// fetchPrice, then read back the rendered DOM. This proves whether the
		// live JS actually paints the subtotal (vs. a stale cached bundle).
		var mcb = document.getElementById( 'bmm_wants_membership' );
		if ( mcb && typeof window.bmmFetchPrice === 'function' ) {
			var wasChecked = mcb.checked;
			mcb.checked = true;
			window.bmmFetchPrice();
			setTimeout( function () {
				var amt = document.getElementById( 'bmm-preview-subtotal-amount' );
				var row = document.getElementById( 'bmm-preview-subtotal' );
				box.textContent += '\n\nAFTER real fetchPrice():'
					+ '\n  subtotal amount text: "' + ( amt ? amt.textContent : 'NO EL' ) + '"'
					+ '\n  subtotal row hidden: ' + ( row ? row.hidden : 'NO EL' );
				mcb.checked = wasChecked;
			}, 1500 );
		}

		// Exercise the /submit path in dry-run mode (no DB write) with dummy
		// valid data, so we can see whether submit would succeed and return the
		// Nedarim config the iframe needs. This is the gate before step 6.
		fetch( <?php echo wp_json_encode( $submit_ep ); ?>, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( {
				form_id: <?php echo $fid; ?>,
				dry_run: true,
				first_name: 'Test', last_name: 'Test', phone: '0500000000',
				email: 'test@example.com', hebrew_name: 'בדיקה', tribe: 'yisrael',
				payment_type: 'Ragil', wants_membership: true,
				seats_men: {}, seats_women: {}, sponsorship_ids: []
			} )
		} )
		.then( function ( r ) { return r.text().then( function ( t ) {
			box.textContent += '\n\nsubmit (dry-run) → HTTP ' + r.status + '\n' + t.slice( 0, 400 );
		} ); } )
		.catch( function ( e ) { box.textContent += '\n\nsubmit (dry-run) → FETCH ERROR: ' + e; } );

		// "Simulate Nedarim callback" button: creates a TEST submission and runs
		// the real completion path, so the post-payment flow can be verified
		// without a live card. Admin-only endpoint, authenticated via wp_rest nonce.
		var simBtn = document.getElementById( 'bmm-simulate-callback' );
		var simOut = document.getElementById( 'bmm-simulate-result' );

		// Collect what the admin actually entered on the form so the simulated
		// TEST submission reflects it — including the per-davening seat inputs,
		// which bmm-seats.js keeps in sync when "Same for all davenings" is on.
		function collectSimData() {
			var wrap = document.getElementById( 'bmm-registration' );
			var data = { form_id: <?php echo $fid; ?> };
			if ( ! wrap ) { return data; }
			[ 'first_name', 'last_name', 'phone', 'email', 'city', 'address', 'zeout', 'hebrew_name' ].forEach( function ( n ) {
				var el = wrap.querySelector( '[name="' + n + '"]' );
				if ( el ) { data[ n ] = el.value; }
			} );
			var tribe = wrap.querySelector( '[name="tribe"]:checked' );
			data.tribe = tribe ? tribe.value : 'yisrael';
			function checked( id ) { var el = wrap.querySelector( id ); return !! ( el && el.checked ); }
			data.wants_membership  = checked( '#bmm_wants_membership' );
			data.has_horaat_keva   = checked( '#bmm_has_horaat_keva' );
			data.wants_guest_seats = checked( '#bmm_wants_guest_seats' );
			var men = {}, women = {};
			( ( window.bmmConfig && window.bmmConfig.davenings ) || [] ).forEach( function ( k ) {
				var m = wrap.querySelector( '[name="seats_men[' + k + ']"]' );
				var w = wrap.querySelector( '[name="seats_women[' + k + ']"]' );
				men[ k ]   = m ? Math.max( 0, parseInt( m.value, 10 ) || 0 ) : 0;
				women[ k ] = w ? Math.max( 0, parseInt( w.value, 10 ) || 0 ) : 0;
			} );
			data.seats_men = men;
			data.seats_women = women;
			data.payment_type = 'Ragil';
			return data;
		}

		if ( simBtn && simOut ) {
			simBtn.addEventListener( 'click', function () {
				simBtn.disabled = true;
				simOut.textContent = 'Simulating…';
				fetch( <?php echo wp_json_encode( $simulate_ep ); ?>, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': <?php echo wp_json_encode( $rest_nonce ); ?>
					},
					body: JSON.stringify( collectSimData() )
				} )
				.then( function ( r ) { return r.text().then( function ( t ) {
					simOut.textContent = 'simulate-callback → HTTP ' + r.status + '\n' + t;
					try {
						var j = JSON.parse( t );
						if ( j.admin_link ) {
							simOut.innerHTML += '<br><a href="' + j.admin_link + '" target="_blank">View the TEST submission in admin →</a>';
						}
					} catch ( e ) {}
					simBtn.disabled = false;
				} ); } )
				.catch( function ( e ) { simOut.textContent = 'FETCH ERROR: ' + e; simBtn.disabled = false; } );
			} );
		}
	} );
} )();
</script>
		<?php
		return (string) ob_get_clean();
	}

	public static function enqueue_assets( BMM_Form_Config $form ): void {
		wp_enqueue_style( 'bmm-form', BMM_REG_URL . 'assets/css/bmm-form.css', [], BMM_REG_VERSION );
		wp_enqueue_script( 'bmm-form',    BMM_REG_URL . 'assets/js/bmm-form.js',    [], BMM_REG_VERSION, true );
		wp_enqueue_script( 'bmm-seats',   BMM_REG_URL . 'assets/js/bmm-seats.js',   [ 'bmm-form' ], BMM_REG_VERSION, true );
		wp_enqueue_script( 'bmm-pricing', BMM_REG_URL . 'assets/js/bmm-pricing.js', [ 'bmm-form' ], BMM_REG_VERSION, true );
		wp_enqueue_script( 'bmm-nedarim', BMM_REG_URL . 'assets/js/bmm-nedarim.js', [ 'bmm-form' ], BMM_REG_VERSION, true );

		wp_localize_script( 'bmm-form', 'bmmConfig', [
			'formId'          => $form->post_id,
			'formTitle'       => $form->title,
			'priceEndpoint'   => rest_url( 'bmm/v1/calculate-price' ),
			'submitEndpoint'  => rest_url( 'bmm/v1/submit' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'maxInstallments' => $form->max_installments,
			'sponsorships'    => $form->enabled_sponsorships(),
			'membershipPrice' => $form->membership_price,
			'includedMen'     => $form->membership_included_men,
			'includedWomen'   => $form->membership_included_women,
			'extraSeatPrice'  => $form->extra_seat_price,
				'guestSeatPrice'  => $form->guest_seat_price,
			'davenings'       => array_keys( BMM_Pricing::DAVENINGS ),
			'daveningLabels'  => BMM_Pricing::DAVENINGS,
			'i18n'            => [
				'required'        => __( 'This field is required.', 'bmm-registration' ),
				'invalidEmail'    => __( 'Please enter a valid email address.', 'bmm-registration' ),
				'paymentError'    => __( 'Payment failed. Please try again.', 'bmm-registration' ),
				'paymentSuccess'  => __( 'Payment completed successfully!', 'bmm-registration' ),
				'submitting'      => __( 'Saving registration...', 'bmm-registration' ),
				'processing'      => __( 'Processing payment...', 'bmm-registration' ),
				'kiddushDate'            => __( 'Date', 'bmm-registration' ),
				'kiddushDedicationLabel' => __( 'Dedication', 'bmm-registration' ),
				'kiddushDedication'      => __( "Birthday, anniversary, l'ilur nishmas...", 'bmm-registration' ),
			],
		] );
	}

	private static function get_html( BMM_Form_Config $form ): string {
		$before = apply_filters( 'bmm_form_before_html', '' );
		ob_start();
		$steps = [
			1 => __( 'Personal Info', 'bmm-registration' ),
			2 => __( 'Hebrew Names', 'bmm-registration' ),
			3 => __( 'Membership & Seats', 'bmm-registration' ),
			4 => __( 'Sponsorships', 'bmm-registration' ),
			5 => __( 'Notes & Payment', 'bmm-registration' ),
			6 => __( 'Complete Payment', 'bmm-registration' ),
		];
		require BMM_REG_DIR . 'public/views/form-wrapper.php';
		return $before . ob_get_clean();
	}
}
