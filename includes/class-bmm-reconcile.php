<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reconcile pending submissions against Nedarim's cleared-transaction history.
 *
 * Nedarim sends each completion callback once with no retry, so a lost callback
 * leaves a paid submission stuck on "Pending". This pulls the authoritative list
 * of transactions from Nedarim and proposes matches to pending submissions, so
 * an admin can confirm and complete them. Matching is fuzzy (amount + a contact
 * field, since Nedarim does not store our submission id), hence confirm-first.
 */
class BMM_Reconcile {

	private const HISTORY_URL = 'https://matara.pro/nedarimplus/Reports/Manage3.aspx';

	/**
	 * Pull recent transactions from Nedarim. Returns a list of raw transaction
	 * arrays, or WP_Error on failure.
	 *
	 * @return array[]|\WP_Error
	 */
	public static function fetch_transactions( string $mosad, string $api_password ) {
		$mosad        = trim( $mosad );
		$api_password = trim( $api_password );
		if ( $mosad === '' || $api_password === '' ) {
			return new \WP_Error( 'missing_creds', __( 'Set the Nedarim Institution ID (Mosad) and API Password in Settings first.', 'bmm-registration' ) );
		}

		$res = wp_remote_post( self::HISTORY_URL, [
			'timeout' => 30,
			'body'    => [
				'Action'      => 'GetHistoryJson',
				'Mosad'       => $mosad,
				'ApiPassword' => $api_password,
				'MaxId'       => '2000',
			],
		] );

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		if ( $code !== 200 ) {
			return new \WP_Error( 'http_error', sprintf( /* translators: %d: HTTP status */ __( 'Nedarim returned HTTP %d.', 'bmm-registration' ), $code ) );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'bad_response', __( 'Could not read Nedarim response. Check the API Password.', 'bmm-registration' ), [ 'body' => mb_substr( $body, 0, 300 ) ] );
		}

		return self::extract_transactions( $decoded );
	}

	/**
	 * The history endpoint may return a bare JSON array of transactions or wrap
	 * it in an object. Pull out the list of transaction rows defensively. Pure.
	 */
	public static function extract_transactions( array $decoded ): array {
		// A bare list of transaction rows.
		if ( isset( $decoded[0] ) && is_array( $decoded[0] ) ) {
			return array_values( array_filter( $decoded, 'is_array' ) );
		}
		// Wrapped: find the first value that is a list of arrays.
		foreach ( $decoded as $value ) {
			if ( is_array( $value ) && isset( $value[0] ) && is_array( $value[0] ) ) {
				return array_values( array_filter( $value, 'is_array' ) );
			}
		}
		return [];
	}

	/**
	 * Match pending submissions to cleared transactions. Pure — the money-safe
	 * core, unit-tested.
	 *
	 * A transaction matches a pending submission when the amount is equal AND at
	 * least one contact field (phone, Israeli ID, or email) matches — amount
	 * alone is never enough. Each transaction is used at most once; the strongest
	 * identity match wins.
	 *
	 * @param array $pendings     Each: [ id, total, phone, zeout, email ].
	 * @param array $transactions Raw Nedarim rows (Amount, Phone, Zeout, Mail, TransactionId, …).
	 * @return array Each match: [ submission_id, txn => [normalized], reasons => [] ].
	 */
	public static function match( array $pendings, array $transactions ): array {
		$txns = [];
		foreach ( $transactions as $k => $t ) {
			$txns[ $k ] = [
				'amount'       => (int) round( (float) ( $t['Amount'] ?? 0 ) ),
				'phone'        => preg_replace( '/\D/', '', (string) ( $t['Phone'] ?? '' ) ),
				'zeout'        => trim( (string) ( $t['Zeout'] ?? '' ) ),
				'email'        => strtolower( trim( (string) ( $t['Mail'] ?? '' ) ) ),
				'txn_id'       => trim( (string) ( $t['TransactionId'] ?? '' ) ),
				'confirmation' => trim( (string) ( $t['Confirmation'] ?? '' ) ),
				'last4'        => trim( (string) ( $t['LastNum'] ?? '' ) ),
				'time'         => trim( (string) ( $t['TransactionTime'] ?? '' ) ),
			];
		}

		$used    = [];
		$matches = [];
		foreach ( $pendings as $p ) {
			$total = (int) ( $p['total'] ?? 0 );
			$phone = preg_replace( '/\D/', '', (string) ( $p['phone'] ?? '' ) );
			$zeout = trim( (string) ( $p['zeout'] ?? '' ) );
			$email = strtolower( trim( (string) ( $p['email'] ?? '' ) ) );

			$best_key = null;
			$best     = 0;
			$best_reasons = [];

			foreach ( $txns as $k => $t ) {
				if ( isset( $used[ $k ] ) || $t['amount'] !== $total ) {
					continue;
				}
				$score   = 0;
				$reasons = [];
				if ( $phone !== '' && $phone === $t['phone'] ) { $score++; $reasons[] = 'phone'; }
				if ( $zeout !== '' && $zeout === $t['zeout'] ) { $score++; $reasons[] = 'ID'; }
				if ( $email !== '' && $email === $t['email'] ) { $score++; $reasons[] = 'email'; }
				if ( $score > $best ) {
					$best         = $score;
					$best_key     = $k;
					$best_reasons = $reasons;
				}
			}

			if ( $best_key !== null ) {
				$used[ $best_key ] = true;
				$matches[] = [
					'submission_id' => (int) ( $p['id'] ?? 0 ),
					'txn'           => $txns[ $best_key ],
					'reasons'       => $best_reasons,
				];
			}
		}

		return $matches;
	}
}
