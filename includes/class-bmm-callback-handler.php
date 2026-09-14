<?php
defined( 'ABSPATH' ) || exit;

class BMM_Callback_Handler {

	private const NEDARIM_IP = '18.194.219.73';

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$remote_ip = $this->get_request_ip();
		$ip_ok     = $this->verify_ip();

		// Parse the body up front so every attempt — even a rejected one — can be
		// logged with the submission it referenced. Nedarim retries a callback
		// zero times, so a rejected/lost one silently strands a paid submission;
		// the log is how we find those.
		$payload = json_decode( $request->get_body(), true );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}
		$token         = sanitize_text_field( $request->get_param( 'token' ) ?? '' );
		$submission_id = (int) ( $payload['Param1'] ?? 0 );

		$log = [
			'ip'            => $remote_ip,
			'ip_ok'         => $ip_ok ? 1 : 0,
			'submission_id' => $submission_id,
			'amount'        => isset( $payload['Amount'] ) ? (string) $payload['Amount'] : '',
			'txn'           => isset( $payload['TransactionId'] ) ? (string) $payload['TransactionId'] : '',
			'keva'          => isset( $payload['KevaId'] ) ? (string) $payload['KevaId'] : '',
		];

		$finish = static function ( string $outcome, int $status ) use ( &$log ): \WP_REST_Response {
			$log['outcome'] = $outcome;
			BMM_Callback_Log::record( $log );
			return new \WP_REST_Response( [ 'received' => true, 'outcome' => $outcome ], $status );
		};

		// 1. IP verification.
		if ( ! $ip_ok ) {
			return $finish( 'forbidden_ip', 403 );
		}

		// 2. Body must be valid JSON.
		if ( ! $payload ) {
			return $finish( 'invalid_payload', 400 );
		}

		// 3. Token + submission id present.
		if ( ! $submission_id || ! $token ) {
			return $finish( 'missing_id_or_token', 200 );
		}

		$stored_token = get_post_meta( $submission_id, '_bmm_sub_callback_token', true );
		if ( ! hash_equals( (string) $stored_token, $token ) ) {
			return $finish( 'invalid_token', 403 );
		}

		// 4. Validate submission post.
		$post = get_post( $submission_id );
		if ( ! $post || $post->post_type !== 'bmm_submission' ) {
			return $finish( 'submission_not_found', 200 );
		}

		// 5. Validate parent is a valid form.
		$parent = get_post( $post->post_parent );
		if ( ! $parent || $parent->post_type !== 'bmm_reg_form' ) {
			return $finish( 'invalid_form', 200 );
		}

		// 6. Idempotency: already completed.
		if ( $post->post_status === 'completed' ) {
			return $finish( 'already_completed', 200 );
		}

		// 7. Determine HK vs regular.
		$is_hk = ! empty( $payload['KevaId'] ) && empty( $payload['TransactionId'] );

		// 8. Only complete when the callback evidences an actually-approved
		//    payment. Declined/errored attempts are recorded and left pending.
		if ( ! self::payment_succeeded( $payload ) ) {
			BMM_Submission::record_failed_attempt( $submission_id, $payload );
			return $finish( 'not_approved', 200 );
		}

		// 9. Complete the submission.
		BMM_Submission::complete( $submission_id, $payload, $is_hk );
		return $finish( 'completed', 200 );
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
				return self::normalize_ip( explode( ',', $forwarded )[0] );
			}
		}
		return self::normalize_ip( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/**
	 * Normalise a remote address so the exact-match IP check is not defeated by
	 * an IPv4-mapped IPv6 form ("::ffff:18.194.219.73") — a common way a real
	 * Nedarim callback gets silently rejected, leaving a paid submission pending.
	 * Pure, so it can be unit-tested.
	 */
	public static function normalize_ip( string $ip ): string {
		$ip = trim( $ip );
		if ( stripos( $ip, '::ffff:' ) === 0 ) {
			$ip = substr( $ip, 7 );
		}
		return $ip;
	}
}
