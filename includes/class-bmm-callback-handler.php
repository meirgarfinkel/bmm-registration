<?php
defined( 'ABSPATH' ) || exit;

class BMM_Callback_Handler {

	private const NEDARIM_IP = '18.194.219.73';

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// 1. IP verification
		if ( ! $this->verify_ip() ) {
			return new \WP_REST_Response( [ 'error' => 'Forbidden' ], 403 );
		}

		// 2. Parse JSON body
		$payload = json_decode( $request->get_body(), true );
		if ( ! is_array( $payload ) ) {
			return new \WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
		}

		// 3. Token verification
		$token         = sanitize_text_field( $request->get_param( 'token' ) ?? '' );
		$submission_id = (int) ( $payload['Param1'] ?? 0 );

		if ( ! $submission_id || ! $token ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		$stored_token = get_post_meta( $submission_id, '_bmm_sub_callback_token', true );
		if ( ! hash_equals( (string) $stored_token, $token ) ) {
			return new \WP_REST_Response( [ 'error' => 'Invalid token' ], 403 );
		}

		// 4. Validate submission post
		$post = get_post( $submission_id );
		if ( ! $post || $post->post_type !== 'bmm_submission' ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		// 5. Validate parent is a valid form
		$parent = get_post( $post->post_parent );
		if ( ! $parent || $parent->post_type !== 'bmm_reg_form' ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		// 6. Idempotency: already completed
		if ( $post->post_status === 'completed' ) {
			return new \WP_REST_Response( [ 'received' => true ], 200 );
		}

		// 7. Determine HK vs regular
		$is_hk = ! empty( $payload['KevaId'] ) && empty( $payload['TransactionId'] );

		// 8. Complete the submission
		BMM_Submission::complete( $submission_id, $payload, $is_hk );

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	private function verify_ip(): bool {
		$remote_ip = $this->get_request_ip();

		// Allow bypassing in local/dev environments
		if ( defined( 'BMM_SKIP_IP_CHECK' ) && BMM_SKIP_IP_CHECK ) {
			return true;
		}

		return $remote_ip === self::NEDARIM_IP;
	}

	private function get_request_ip(): string {
		if ( defined( 'BMM_TRUST_PROXY' ) && BMM_TRUST_PROXY ) {
			$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
			if ( $forwarded ) {
				return trim( explode( ',', $forwarded )[0] );
			}
		}
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}
}
