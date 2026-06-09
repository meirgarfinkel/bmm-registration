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
		return self::get_html( $form );
	}

	private static function enqueue_assets( BMM_Form_Config $form ): void {
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
			'nonce'           => wp_create_nonce( 'bmm_submit' ),
			'paymentOptions'  => $form->payment_options,
			'sponsorships'    => $form->enabled_sponsorships(),
			'membershipPrice' => $form->membership_price,
			'includedMen'     => $form->membership_included_men,
			'includedWomen'   => $form->membership_included_women,
			'extraSeatPrice'  => $form->extra_seat_price,
			'davenings'       => array_keys( BMM_Pricing::DAVENINGS ),
			'daveningLabels'  => BMM_Pricing::DAVENINGS,
			'i18n'            => [
				'required'        => __( 'This field is required.', 'bmm-registration' ),
				'invalidEmail'    => __( 'Please enter a valid email address.', 'bmm-registration' ),
				'paymentError'    => __( 'Payment failed. Please try again.', 'bmm-registration' ),
				'paymentSuccess'  => __( 'Payment completed successfully!', 'bmm-registration' ),
				'submitting'      => __( 'Saving registration...', 'bmm-registration' ),
				'processing'      => __( 'Processing payment...', 'bmm-registration' ),
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
