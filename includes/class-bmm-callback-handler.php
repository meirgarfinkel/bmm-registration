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

		// 8. Only complete when the callback evidences an actually-approved
		//    payment. Nedarim posts the CallBack for declined/errored attempts
		//    too; those must NOT flip the submission to "completed" — they are
		//    recorded for audit and the submission stays pending (so a later
		//    successful retry can still complete it).
		if ( ! self::payment_succeeded( $payload ) ) {
			BMM_Submission::record_failed_attempt( $submission_id, $payload );
			return new \WP_REST_Response( [ 'received' => true, 'completed' => false ], 200 );
		}

		// 9. Complete the submission
		BMM_Submission::complete( $submission_id, $payload, $is_hk );

		return new \WP_REST_Response( [ 'received' => true, 'completed' => true ], 200 );
	}

	/**
	 * Decide whether a Nedarim callback payload represents an actually-approved
	 * payment. Pure (no I/O) so it can be unit-tested directly.
	 *
	 * A submission may only be marked "completed" when the payload carries
	 * positive evidence of success:
	 *   - a real, non-zero TransactionId (regular credit-card charge), or
	 *   - a KevaId (Horaat Keva standing-order establishment), or
	 *   - an explicit success Status/Result field.
	 * An explicit failure Status (Error/Declined/…) always loses, even if a
	 * stale identifier is present. A payload with no success evidence (e.g.
	 * Status=Error, TransactionId empty or "0") is a failed attempt.
	 */
	public static function payment_succeeded( array $payload ): bool {
		$status = strtolower( trim( (string) ( $payload['Status'] ?? $payload['Result'] ?? '' ) ) );

		// An explicit failure verdict is authoritative.
		if ( in_array( $status, [ 'error', 'fail', 'failed', 'decline', 'declined', 'rejected', '0' ], true ) ) {
			return false;
		}

		$txn  = trim( (string) ( $payload['TransactionId'] ?? '' ) );
		$keva = trim( (string) ( $payload['KevaId'] ?? '' ) );

		if ( $txn !== '' && $txn !== '0' ) {
			return true;
		}
		if ( $keva !== '' && $keva !== '0' ) {
			return true;
		}

		// No identifier, but an explicit success verdict.
		return in_array( $status, [ 'ok', 'success', 'completed', 'true', '1' ], true );
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
