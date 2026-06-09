<?php
defined( 'ABSPATH' ) || exit;

class BMM_REST_Submit extends \WP_REST_Controller {

	protected $namespace = 'bmm/v1';
	protected $rest_base = 'submit';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'submit' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function submit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Nonce check
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?? $request->get_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'bmm_submit' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'bmm-registration' ), [ 'status' => 403 ] );
		}

		$data    = $request->get_params();
		$form_id = (int) ( $data['form_id'] ?? 0 );

		// Validate form
		try {
			$form = new BMM_Form_Config( $form_id );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'invalid_form', __( 'Form not found.', 'bmm-registration' ), [ 'status' => 404 ] );
		}

		if ( ! $form->is_published() ) {
			return new \WP_Error( 'form_closed', __( 'This registration form is not currently open.', 'bmm-registration' ), [ 'status' => 403 ] );
		}

		// Validate required fields
		$errors = $this->validate( $data, $form );
		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_error', __( 'Please correct the highlighted fields.', 'bmm-registration' ), [
				'status' => 422,
				'errors' => $errors,
			] );
		}

		// Calculate authoritative price
		$pricing = BMM_Pricing::calculate( $form, $data );

		// Create submission
		try {
			$submission_id = BMM_Submission::create_draft( $form_id, $data, $pricing );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'submission_failed', __( 'Could not save your registration. Please try again.', 'bmm-registration' ), [ 'status' => 500 ] );
		}

		// Build callback URL with token
		$token        = get_post_meta( $submission_id, '_bmm_sub_callback_token', true );
		$callback_url = add_query_arg( 'token', $token, rest_url( 'bmm/v1/nedarim-callback' ) );

		// Build summary for Nedarim Comment field (≤300 chars)
		$comment = $this->build_comment( $data, $pricing );

		return new \WP_REST_Response( [
			'submission_id' => $submission_id,
			'total'         => $pricing['total'],
			'itemized'      => $pricing,
			'mosad'         => $form->mosad,
			'api_valid'     => $form->api_valid,
			'payment_type'  => in_array( $data['payment_type'] ?? 'Ragil', [ 'Ragil', 'HK' ], true ) ? $data['payment_type'] : 'Ragil',
			'tashlumim'     => ( $data['payment_type'] ?? 'Ragil' ) === 'HK' ? (string) $form->hk_months : (string) $form->ragil_tashlumim,
			'callback_url'  => $callback_url,
			'comment'       => $comment,
		], 200 );
	}

	private function validate( array $data, BMM_Form_Config $form ): array {
		$errors = [];

		if ( empty( trim( $data['first_name'] ?? '' ) ) ) {
			$errors['first_name'] = __( 'First name is required.', 'bmm-registration' );
		}
		if ( empty( trim( $data['last_name'] ?? '' ) ) ) {
			$errors['last_name'] = __( 'Last name is required.', 'bmm-registration' );
		}
		if ( empty( trim( $data['phone'] ?? '' ) ) ) {
			$errors['phone'] = __( 'Phone number is required.', 'bmm-registration' );
		}
		if ( empty( trim( $data['email'] ?? '' ) ) || ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'A valid email address is required.', 'bmm-registration' );
		}
		if ( empty( trim( $data['hebrew_name'] ?? '' ) ) ) {
			$errors['hebrew_name'] = __( 'Hebrew name is required.', 'bmm-registration' );
		}
		if ( ! in_array( $data['tribe'] ?? '', [ 'kohen', 'levi', 'yisrael' ], true ) ) {
			$errors['tribe'] = __( 'Please select your tribe.', 'bmm-registration' );
		}

		// Validate payment type is allowed by form
		$payment_type = $data['payment_type'] ?? 'Ragil';
		$allowed = $form->payment_options;
		if ( $allowed !== 'both' ) {
			$expected = $allowed === 'hk' ? 'HK' : 'Ragil';
			if ( $payment_type !== $expected ) {
				$errors['payment_type'] = __( 'Invalid payment type.', 'bmm-registration' );
			}
		} elseif ( ! in_array( $payment_type, [ 'Ragil', 'HK' ], true ) ) {
			$errors['payment_type'] = __( 'Invalid payment type.', 'bmm-registration' );
		}

		return $errors;
	}

	private function build_comment( array $data, array $pricing ): string {
		$parts = [];

		if ( $pricing['membership'] > 0 ) {
			$parts[] = 'Membership: ₪' . $pricing['membership'];
		}
		if ( $pricing['extra_men_seats'] > 0 ) {
			$parts[] = 'Extra men seats (' . $pricing['extra_men_count'] . '): ₪' . $pricing['extra_men_seats'];
		}
		if ( $pricing['extra_women_seats'] > 0 ) {
			$parts[] = 'Extra women seats (' . $pricing['extra_women_count'] . '): ₪' . $pricing['extra_women_seats'];
		}
		foreach ( $pricing['sponsorships'] as $s ) {
			$parts[] = $s['label'] . ': ₪' . $s['amount'];
		}
		$parts[] = 'Total: ₪' . $pricing['total'];

		$comment = implode( ' | ', $parts );
		return mb_substr( $comment, 0, 300 );
	}
}
