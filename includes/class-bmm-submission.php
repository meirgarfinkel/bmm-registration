<?php
defined( 'ABSPATH' ) || exit;

class BMM_Submission {

	/**
	 * Create a new pending submission. Returns the post ID.
	 */
	public static function create_draft( int $form_id, array $data, array $pricing ): int {
		$first = sanitize_text_field( $data['first_name'] ?? '' );
		$last  = sanitize_text_field( $data['last_name'] ?? '' );
		$token = wp_generate_password( 32, false );

		$post_id = wp_insert_post( [
			'post_type'   => 'bmm_submission',
			'post_title'  => trim( "$first $last" ) ?: __( 'Registration', 'bmm-registration' ),
			'post_status' => 'bmm_pending',
			'post_parent' => $form_id,
		], true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( $post_id->get_error_message() );
		}

		// Personal info
		self::set_meta( $post_id, [
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => sanitize_email( $data['email'] ?? '' ),
			'phone'      => preg_replace( '/\D/', '', $data['phone'] ?? '' ),
			'city'       => sanitize_text_field( $data['city'] ?? '' ),
			'address'    => sanitize_text_field( $data['address'] ?? '' ),
			'zeout'      => sanitize_text_field( $data['zeout'] ?? '' ),
		] );

		// Hebrew names
		self::set_meta( $post_id, [
			'hebrew_name'           => sanitize_text_field( $data['hebrew_name'] ?? '' ),
			'tribe'                 => sanitize_key( $data['tribe'] ?? 'yisrael' ),
			'wife_hebrew_name'      => sanitize_text_field( $data['wife_hebrew_name'] ?? '' ),
			'children_hebrew_names' => self::encode_json( array_map( 'sanitize_text_field', (array) ( $data['children_hebrew_names'] ?? [] ) ) ),
		] );

		// Membership & seats
		$seats_men   = BMM_Pricing::normalize_seats( (array) ( $data['seats_men'] ?? [] ) );
		$seats_women = BMM_Pricing::normalize_seats( (array) ( $data['seats_women'] ?? [] ) );

		self::set_meta( $post_id, [
			'wants_membership'  => ! empty( $data['wants_membership'] )  ? 1 : 0,
			'has_horaat_keva'   => ! empty( $data['has_horaat_keva'] )   ? 1 : 0,
			'wants_guest_seats' => ! empty( $data['wants_guest_seats'] ) ? 1 : 0,
			'seats_men'         => self::encode_json( $seats_men ),
			'seats_women'       => self::encode_json( $seats_women ),
		] );

		// Sponsorships. Pre-defined sponsorships are stored as their IDs; the
		// free-form "Other" sponsorship is stored as a non-negative amount.
		$sponsorship_ids   = array_map( 'sanitize_key', (array) ( $data['sponsorship_ids'] ?? [] ) );
		$sponsorship_other = max( 0, (int) round( (float) ( $data['sponsorship_other'] ?? 0 ) ) );
		self::set_meta( $post_id, [
			'sponsorships_selected' => self::encode_json( $sponsorship_ids ),
			'sponsorship_other'     => $sponsorship_other,
		] );

		// Kiddush Fund extras: the sponsored date and a dedication line. Only
		// stored when the Kiddush Fund is among the selected sponsorships.
		$is_kiddush = in_array( 'kiddush', $sponsorship_ids, true );
		self::set_meta( $post_id, [
			'kiddush_date'       => $is_kiddush ? self::sanitize_date( $data['kiddush_date'] ?? '' ) : '',
			'kiddush_dedication' => $is_kiddush ? sanitize_text_field( $data['kiddush_dedication'] ?? '' ) : '',
		] );

		// Notes & payment type. The installment count must respect the chosen
		// method: "pay in full" (Ragil) is always a single payment, regardless of
		// whatever the installments selector last held. Only "Tashlumim" keeps the
		// chosen count (clamped to >= 1).
		$payment_type = in_array( $data['payment_type'] ?? 'Ragil', [ 'Ragil', 'Tashlumim' ], true ) ? $data['payment_type'] : 'Ragil';
		$tashlumim    = ( $payment_type === 'Tashlumim' ) ? max( 1, (int) ( $data['tashlumim'] ?? 1 ) ) : 1;
		self::set_meta( $post_id, [
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'payment_type' => $payment_type,
			'tashlumim'    => $tashlumim,
		] );

		// Pricing (locked at submission time)
		self::set_meta( $post_id, [
			'price_membership'    => $pricing['membership'],
			'price_extra_men'     => $pricing['extra_men_seats'],
			'price_extra_women'   => $pricing['extra_women_seats'],
			'price_guest_men'     => $pricing['guest_men_seats'],
			'price_guest_women'   => $pricing['guest_women_seats'],
			'price_sponsorships'  => $pricing['sponsorships_total'],
			'price_total'         => $pricing['total'],
			'callback_token'      => $token,
		] );

		return $post_id;
	}

	/**
	 * Update submission with payment completion data from Nedarim Plus callback.
	 */
	public static function complete( int $post_id, array $payload, bool $is_hk = false ): void {
		$updates = [
			'nedarim_raw_callback'  => self::encode_json( $payload ),
			'payment_completed_at'  => current_time( 'c' ),
		];

		if ( $is_hk ) {
			$updates['nedarim_keva_id'] = sanitize_text_field( $payload['KevaId'] ?? '' );
		} else {
			$updates['nedarim_transaction_id'] = sanitize_text_field( $payload['TransactionId'] ?? '' );
			$updates['nedarim_confirmation']   = sanitize_text_field( $payload['Confirmation'] ?? '' );
			$updates['nedarim_last_num']        = sanitize_text_field( $payload['LastNum'] ?? '' );
		}

		// Cross-check amount
		$stored_total = (int) get_post_meta( $post_id, '_bmm_sub_price_total', true );
		$callback_amount = (int) round( (float) ( $payload['Amount'] ?? 0 ) );
		if ( $stored_total > 0 && $callback_amount !== $stored_total ) {
			$updates['amount_mismatch'] = 1;
		}

		self::set_meta( $post_id, $updates );

		// A real payment landed — clear any "unverified" audit flag.
		delete_post_meta( $post_id, '_bmm_sub_payment_unverified' );

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'completed',
		] );

		do_action( 'bmm_payment_completed', $post_id, $payload );
	}

	/**
	 * Record a Nedarim callback that did NOT represent an approved payment
	 * (declined card, error, or an ambiguous payload with no transaction id).
	 * The submission is intentionally left in its current state — it must never
	 * become "completed" without a real payment — while the raw payload and an
	 * attempt counter are kept for admin review.
	 */
	public static function record_failed_attempt( int $post_id, array $payload ): void {
		$attempts = (int) get_post_meta( $post_id, '_bmm_sub_failed_attempts', true );
		self::set_meta( $post_id, [
			'failed_attempts'      => $attempts + 1,
			'last_failed_callback' => self::encode_json( $payload ),
		] );

		do_action( 'bmm_payment_failed_attempt', $post_id, $payload );
	}

	public static function fail( int $post_id ): void {
		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'failed',
		] );
	}

	public static function get_meta( int $post_id ): array {
		$keys = [
			'first_name', 'last_name', 'email', 'phone', 'city', 'address', 'zeout',
			'hebrew_name', 'tribe', 'wife_hebrew_name', 'children_hebrew_names',
			'wants_membership', 'has_horaat_keva', 'wants_guest_seats', 'seats_men', 'seats_women', 'sponsorships_selected', 'sponsorship_other',
			'kiddush_date', 'kiddush_dedication',
			'notes', 'payment_type', 'tashlumim',
			'price_membership', 'price_extra_men', 'price_extra_women', 'price_sponsorships', 'price_total',
			'nedarim_transaction_id', 'nedarim_keva_id', 'nedarim_confirmation', 'nedarim_last_num',
			'payment_completed_at', 'amount_mismatch', 'failed_attempts',
		];

		$result = [];
		foreach ( $keys as $key ) {
			$result[ $key ] = get_post_meta( $post_id, "_bmm_sub_{$key}", true );
		}

		// Decode JSON fields
		foreach ( [ 'children_hebrew_names', 'seats_men', 'seats_women', 'sponsorships_selected' ] as $json_key ) {
			if ( $result[ $json_key ] ) {
				$result[ $json_key ] = json_decode( $result[ $json_key ], true ) ?? [];
			} else {
				$result[ $json_key ] = [];
			}
		}

		// Recover legacy submissions whose Hebrew names were mangled by the old
		// encoding bug (\uXXXX escapes left as literal "uXXXX" after wp_unslash).
		$result['children_hebrew_names'] = array_map(
			[ __CLASS__, 'recover_mangled_unicode' ],
			$result['children_hebrew_names']
		);

		return $result;
	}

	/**
	 * JSON-encode a value for storage in post meta.
	 *
	 * Two precautions are required for non-ASCII (e.g. Hebrew) content:
	 *   1. JSON_UNESCAPED_UNICODE keeps characters as readable UTF-8 instead of
	 *      \uXXXX escapes.
	 *   2. wp_slash() so that update_metadata()'s internal wp_unslash() does not
	 *      strip the JSON's own backslashes (the root cause of the mangled
	 *      "uXXXX" children's names).
	 */
	private static function encode_json( $value ): string {
		return wp_slash( (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Restore Hebrew text that was corrupted by the old storage bug. The bug
	 * left JSON unicode escapes (י) as bare "u05d9" tokens after WordPress
	 * stripped the backslashes. Correctly-stored values contain no such tokens,
	 * so they pass through untouched.
	 */
	private static function recover_mangled_unicode( $value ): string {
		$value = (string) $value;
		if ( ! preg_match( '/u05[0-9a-fA-F]{2}/', $value ) ) {
			return $value;
		}
		return (string) preg_replace_callback(
			'/u([0-9a-fA-F]{4})/',
			fn( array $m ): string => mb_convert_encoding( pack( 'n', hexdec( $m[1] ) ), 'UTF-8', 'UTF-16BE' ),
			$value
		);
	}

	/**
	 * Accept only a strict Y-m-d calendar date (as produced by <input type="date">).
	 * Anything malformed or non-existent (e.g. 2026-02-30) becomes an empty string.
	 */
	private static function sanitize_date( $value ): string {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return '';
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}

	private static function set_meta( int $post_id, array $fields ): void {
		foreach ( $fields as $key => $value ) {
			update_post_meta( $post_id, "_bmm_sub_{$key}", $value );
		}
	}
}
