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
		// This is a public, anonymous registration endpoint. We intentionally
		// do NOT require a WordPress nonce here: nonces sent via X-WP-Nonce
		// trigger WordPress's REST cookie-auth check, which rejects the request
		// with 403 ("Cookie check failed") whenever the nonce isn't a fresh
		// 'wp_rest' nonce — which is unreliable for anonymous users and under
		// page caching. The submission only creates a *pending* record; the
		// Nedarim payment step is the authoritative gate.
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

		// Dry run (diagnostic): validate + price + report config WITHOUT
		// creating a submission record. Lets the admin diagnostic confirm the
		// submit path works end-to-end without polluting the database.
		if ( ! empty( $data['dry_run'] ) ) {
			return new \WP_REST_Response( [
				'dry_run'      => true,
				'total'        => $pricing['total'],
				'mosad'        => $form->mosad ?: '(empty)',
				'api_valid'    => $form->api_valid ? '(set)' : '(empty)',
				'payment_type' => in_array( $data['payment_type'] ?? 'Ragil', [ 'Ragil', 'Tashlumim' ], true ) ? $data['payment_type'] : 'Ragil',
			], 200 );
		}

		// Create submission
		try {
			$submission_id = BMM_Submission::create_draft( $form_id, $data, $pricing );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'submission_failed', __( 'Could not save your registration. Please try again.', 'bmm-registration' ), [ 'status' => 500 ] );
		}

		// Zero-total order (e.g. membership paid externally via Horaat Keva with
		// no extra seats): nothing to charge. Complete it now and tell the client
		// to skip the Nedarim payment step.
		if ( $pricing['total'] <= 0 ) {
			BMM_Submission::complete_without_payment( $submission_id );

			return new \WP_REST_Response( [
				'submission_id' => $submission_id,
				'total'         => 0,
				'zero_total'    => true,
				'itemized'      => $pricing,
			], 200 );
		}

		// Build callback URL with token
		$token        = get_post_meta( $submission_id, '_bmm_sub_callback_token', true );
		$callback_url = add_query_arg( 'token', $token, rest_url( 'bmm/v1/nedarim-callback' ) );

		// Build summary for Nedarim Comment field (≤300 chars)
		$comment = $this->build_comment( $data, $pricing );

		// Resolve the number of payments. The customer chose "Ragil" (pay in
		// full) or "Tashlumim" (installments). Nedarim treats both as a Ragil
		// credit-card transaction; the only difference is the Tashlumim count:
		//   Ragil      → 1 payment for the full total
		//   Tashlumim  → split into N payments (2..max), Nedarim divides the total
		$choice = in_array( $data['payment_type'] ?? 'Ragil', [ 'Ragil', 'Tashlumim' ], true ) ? $data['payment_type'] : 'Ragil';
		$chosen = (int) ( $data['tashlumim'] ?? 1 );
		if ( $choice === 'Tashlumim' && $form->max_installments > 1 ) {
			$tashlumim = (string) max( 2, min( $chosen, $form->max_installments ) );
		} else {
			$tashlumim = '1';
		}

		return new \WP_REST_Response( [
			'submission_id' => $submission_id,
			'total'         => $pricing['total'],
			'itemized'      => $pricing,
			'mosad'         => $form->mosad,
			'api_valid'     => $form->api_valid,
			'payment_type'  => 'Ragil',   // Nedarim PaymentType — always a credit-card (Ragil) transaction
			'tashlumim'     => $tashlumim, // 1 = pay in full; N = split into N payments
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

		// Validate payment choice: "Ragil" (pay in full) or "Tashlumim"
		// (installments). Installments require the form to allow them.
		$payment_type = $data['payment_type'] ?? 'Ragil';
		if ( ! in_array( $payment_type, [ 'Ragil', 'Tashlumim' ], true ) ) {
			$errors['payment_type'] = __( 'Invalid payment type.', 'bmm-registration' );
		} elseif ( $payment_type === 'Tashlumim' && $form->max_installments <= 1 ) {
			$errors['payment_type'] = __( 'Installments are not available for this form.', 'bmm-registration' );
		}

		return $errors;
	}

	private function build_comment( array $data, array $pricing ): string {
		$parts = [];

		if ( $pricing['membership'] > 0 ) {
			$parts[] = 'Membership: ₪' . $pricing['membership'];
		}
		if ( ! empty( $pricing['has_horaat_keva'] ) ) {
			$parts[] = 'Membership via Horaat Keva';
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
