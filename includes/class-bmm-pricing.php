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

	// Davenings grouped by holiday. Used for the at-a-glance "seats for RH / YK"
	// summaries in the admin list and CSV export.
	public const HOLIDAYS = [
		'rh' => [ 'rh_night1', 'rh_day1', 'rh_night2', 'rh_day2' ],
		'yk' => [ 'yk_night', 'yk_day' ],
	];

	// Seats included when the registrant already pays membership through a
	// separate Horaat Keva (standing order). No membership fee is charged here;
	// these seats are included free, and anything beyond them is billed at the
	// member extra-seat rate. Kept in sync with the Step 3 checkbox label.
	public const HK_INCLUDED_MEN   = 1;
	public const HK_INCLUDED_WOMEN = 1;

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
		// Three mutually exclusive seat modes, in priority order:
		//   membership       — pay the annual membership fee here
		//   horaat_keva      — already pay membership via a separate standing order
		//   guest_seats      — no membership; every seat billed at the guest rate
		$wants_membership  = ! empty( $data['wants_membership'] );
		$has_horaat_keva   = ! empty( $data['has_horaat_keva'] ) && ! $wants_membership;
		$wants_guest_seats = ! empty( $data['wants_guest_seats'] ) && ! $wants_membership && ! $has_horaat_keva;
		$seats_men         = self::normalize_seats( $data['seats_men'] ?? [] );
		$seats_women       = self::normalize_seats( $data['seats_women'] ?? [] );
		$sponsorship_ids   = array_map( 'strval', (array) ( $data['sponsorship_ids'] ?? [] ) );

		$max_men   = $seats_men   ? max( $seats_men )   : 0;
		$max_women = $seats_women ? max( $seats_women ) : 0;

		// ── Membership path ───────────────────────────────────────────────────
		$membership_fee    = 0;
		$extra_men_count   = 0;
		$extra_women_count = 0;
		$extra_men_fee     = 0;
		$extra_women_fee   = 0;

		if ( $wants_membership || $has_horaat_keva ) {
			if ( $wants_membership ) {
				$membership_fee = $form->membership_price;
				$included_men   = $form->membership_included_men;
				$included_women = $form->membership_included_women;
			} else {
				// Horaat Keva: membership already paid separately (no fee here),
				// with a fixed 1 men's + 1 women's seat included.
				$membership_fee = 0;
				$included_men   = self::HK_INCLUDED_MEN;
				$included_women = self::HK_INCLUDED_WOMEN;
			}
			$extra_men_count   = max( 0, $max_men   - $included_men );
			$extra_women_count = max( 0, $max_women - $included_women );
			$extra_men_fee     = $extra_men_count   * $form->extra_seat_price;
			$extra_women_fee   = $extra_women_count * $form->extra_seat_price;
		}

		// ── Guest seats path (mutually exclusive with membership) ─────────────
		$guest_men_count   = 0;
		$guest_women_count = 0;
		$guest_men_fee     = 0;
		$guest_women_fee   = 0;

		if ( $wants_guest_seats ) {
			$guest_men_count   = $max_men;
			$guest_women_count = $max_women;
			$guest_men_fee     = $guest_men_count   * $form->guest_seat_price;
			$guest_women_fee   = $guest_women_count * $form->guest_seat_price;
		}

		// ── Sponsorships ──────────────────────────────────────────────────────
		$sponsorship_breakdown = [];
		$sponsorships_total    = 0;

		$enabled     = $form->enabled_sponsorships();
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

		// Free-form "Other" sponsorship: any custom amount the donor enters.
		// Negative amounts are never allowed.
		$sponsorship_other = max( 0, (int) round( (float) ( $data['sponsorship_other'] ?? 0 ) ) );
		if ( $sponsorship_other > 0 ) {
			$sponsorship_breakdown[] = [
				'id'     => 'other',
				'label'  => __( 'Other', 'bmm-registration' ),
				'amount' => $sponsorship_other,
			];
			$sponsorships_total += $sponsorship_other;
		}

		$total = $membership_fee + $extra_men_fee + $extra_women_fee
		       + $guest_men_fee + $guest_women_fee
		       + $sponsorships_total;

		return [
			'membership'          => $membership_fee,
			'extra_men_seats'     => $extra_men_fee,
			'extra_women_seats'   => $extra_women_fee,
			'extra_men_count'     => $extra_men_count,
			'extra_women_count'   => $extra_women_count,
			'guest_men_seats'     => $guest_men_fee,
			'guest_women_seats'   => $guest_women_fee,
			'guest_men_count'     => $guest_men_count,
			'guest_women_count'   => $guest_women_count,
			'wants_guest_seats'   => $wants_guest_seats,
			'has_horaat_keva'     => $has_horaat_keva,
			'sponsorships'        => $sponsorship_breakdown,
			'sponsorships_total'  => $sponsorships_total,
			'total'               => $total,
		];
	}

	/**
	 * Peak number of seats reserved for a holiday group — the maximum requested
	 * across that holiday's davenings. A registrant holds one physical seat for
	 * the whole holiday, so the max across its sessions is the seat count (and it
	 * matches how the price is computed from the busiest davening).
	 *
	 * @param array  $seats   Seats keyed (or indexed) by davening.
	 * @param string $holiday 'rh' or 'yk'.
	 */
	public static function seats_for_holiday( array $seats, string $holiday ): int {
		$seats = self::normalize_seats( $seats );
		$keys  = self::HOLIDAYS[ $holiday ] ?? [];
		$vals  = array_map( static fn( string $k ): int => $seats[ $k ] ?? 0, $keys );
		return $vals ? max( $vals ) : 0;
	}

	/**
	 * Sum the per-holiday peak seats across many submissions — for the seat
	 * subtotals on the admin Submissions list.
	 *
	 * @param array $rows Each row: [ 'men' => seatsArray, 'women' => seatsArray ].
	 * @return array{ men_rh:int, women_rh:int, men_yk:int, women_yk:int }
	 */
	public static function sum_seat_totals( array $rows ): array {
		$totals = [ 'men_rh' => 0, 'women_rh' => 0, 'men_yk' => 0, 'women_yk' => 0 ];
		foreach ( $rows as $row ) {
			$men   = (array) ( $row['men']   ?? [] );
			$women = (array) ( $row['women'] ?? [] );
			$totals['men_rh']   += self::seats_for_holiday( $men,   'rh' );
			$totals['women_rh'] += self::seats_for_holiday( $women, 'rh' );
			$totals['men_yk']   += self::seats_for_holiday( $men,   'yk' );
			$totals['women_yk'] += self::seats_for_holiday( $women, 'yk' );
		}
		return $totals;
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
