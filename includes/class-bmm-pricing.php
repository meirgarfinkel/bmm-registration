<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pure, stateless price calculator. No I/O — accepts data, returns breakdown.
 */
class BMM_Pricing {

	public const DAVENINGS = [
		'rh_night1' => 'Rosh Hashana Night 1',
		'rh_day1'   => 'Rosh Hashana Day 1',
		'rh_night2' => 'Rosh Hashana Night 2',
		'rh_day2'   => 'Rosh Hashana Day 2',
		'yk_night'  => 'Yom Kippur Night',
		'yk_day'    => 'Yom Kippur Day',
	];

	/**
	 * Calculate full price breakdown.
	 *
	 * @param BMM_Form_Config $form
	 * @param array $data {
	 *   wants_membership: bool,
	 *   seats_men: int[6],
	 *   seats_women: int[6],
	 *   sponsorship_ids: string[],
	 * }
	 * @return array {
	 *   membership: int,
	 *   extra_men_seats: int,
	 *   extra_women_seats: int,
	 *   extra_men_count: int,
	 *   extra_women_count: int,
	 *   sponsorships: array,
	 *   sponsorships_total: int,
	 *   total: int,
	 * }
	 */
	public static function calculate( BMM_Form_Config $form, array $data ): array {
		$wants_membership = ! empty( $data['wants_membership'] );
		$seats_men        = self::normalize_seats( $data['seats_men'] ?? [] );
		$seats_women      = self::normalize_seats( $data['seats_women'] ?? [] );
		$sponsorship_ids  = array_map( 'strval', (array) ( $data['sponsorship_ids'] ?? [] ) );

		// Membership
		$membership_fee = $wants_membership ? $form->membership_price : 0;

		// Extra seats: max across all 6 davenings minus included seats
		$included_men   = $wants_membership ? $form->membership_included_men   : 0;
		$included_women = $wants_membership ? $form->membership_included_women : 0;

		$max_men   = $seats_men   ? max( $seats_men )   : 0;
		$max_women = $seats_women ? max( $seats_women ) : 0;

		$extra_men_count   = max( 0, $max_men   - $included_men );
		$extra_women_count = max( 0, $max_women - $included_women );

		$extra_men_fee   = $extra_men_count   * $form->extra_seat_price;
		$extra_women_fee = $extra_women_count * $form->extra_seat_price;

		// Sponsorships
		$sponsorship_breakdown = [];
		$sponsorships_total    = 0;

		$enabled = $form->enabled_sponsorships();
		$enabled_map = array_column( $enabled, null, 'id' );

		foreach ( $sponsorship_ids as $id ) {
			if ( isset( $enabled_map[ $id ] ) ) {
				$s = $enabled_map[ $id ];
				$sponsorship_breakdown[] = [
					'id'     => $s['id'],
					'label'  => $s['label'],
					'amount' => (int) $s['amount'],
				];
				$sponsorships_total += (int) $s['amount'];
			}
		}

		$total = $membership_fee + $extra_men_fee + $extra_women_fee + $sponsorships_total;

		return [
			'membership'         => $membership_fee,
			'extra_men_seats'    => $extra_men_fee,
			'extra_women_seats'  => $extra_women_fee,
			'extra_men_count'    => $extra_men_count,
			'extra_women_count'  => $extra_women_count,
			'sponsorships'       => $sponsorship_breakdown,
			'sponsorships_total' => $sponsorships_total,
			'total'              => $total,
		];
	}

	/** Normalize seats array to exactly 6 non-negative ints. */
	public static function normalize_seats( array $raw ): array {
		$seats = [];
		$keys  = array_keys( self::DAVENINGS );
		foreach ( $keys as $i => $key ) {
			// Accept either indexed or keyed array
			$val = $raw[ $key ] ?? $raw[ $i ] ?? 0;
			$seats[ $key ] = max( 0, (int) $val );
		}
		return $seats;
	}
}
