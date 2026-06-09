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
			'children_hebrew_names' => wp_json_encode( array_map( 'sanitize_text_field', (array) ( $data['children_hebrew_names'] ?? [] ) ) ),
		] );

		// Membership & seats
		$seats_men   = BMM_Pricing::normalize_seats( (array) ( $data['seats_men'] ?? [] ) );
		$seats_women = BMM_Pricing::normalize_seats( (array) ( $data['seats_women'] ?? [] ) );

		self::set_meta( $post_id, [
			'wants_membership' => ! empty( $data['wants_membership'] ) ? 1 : 0,
			'seats_men'        => wp_json_encode( $seats_men ),
			'seats_women'      => wp_json_encode( $seats_women ),
		] );

		// Sponsorships
		$sponsorship_ids = array_map( 'sanitize_key', (array) ( $data['sponsorship_ids'] ?? [] ) );
		self::set_meta( $post_id, [
			'sponsorships_selected' => wp_json_encode( $sponsorship_ids ),
		] );

		// Notes & payment type
		self::set_meta( $post_id, [
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'payment_type' => in_array( $data['payment_type'] ?? 'Ragil', [ 'Ragil', 'HK' ], true ) ? $data['payment_type'] : 'Ragil',
		] );

		// Pricing (locked at submission time)
		self::set_meta( $post_id, [
			'price_membership'    => $pricing['membership'],
			'price_extra_men'     => $pricing['extra_men_seats'],
			'price_extra_women'   => $pricing['extra_women_seats'],
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
			'nedarim_raw_callback'  => wp_json_encode( $payload ),
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

		wp_update_post( [
			'ID'          => $post_id,
			'post_status' => 'completed',
		] );

		do_action( 'bmm_payment_completed', $post_id, $payload );
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
			'wants_membership', 'seats_men', 'seats_women', 'sponsorships_selected',
			'notes', 'payment_type',
			'price_membership', 'price_extra_men', 'price_extra_women', 'price_sponsorships', 'price_total',
			'nedarim_transaction_id', 'nedarim_keva_id', 'nedarim_confirmation', 'nedarim_last_num',
			'payment_completed_at', 'amount_mismatch',
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

		return $result;
	}

	private static function set_meta( int $post_id, array $fields ): void {
		foreach ( $fields as $key => $value ) {
			update_post_meta( $post_id, "_bmm_sub_{$key}", $value );
		}
	}
}
