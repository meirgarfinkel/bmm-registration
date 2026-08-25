<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( BMM_Pricing::class )]
final class PricingTest extends TestCase {

	/**
	 * Build a BMM_Form_Config double without running its WordPress-dependent
	 * constructor. BMM_Pricing::calculate() only reads public properties and
	 * enabled_sponsorships(), so setting those is enough.
	 */
	private function makeForm( array $overrides = [] ): BMM_Form_Config {
		$form = ( new ReflectionClass( BMM_Form_Config::class ) )->newInstanceWithoutConstructor();
		$form->membership_price          = $overrides['membership_price']          ?? 500;
		$form->membership_included_men   = $overrides['membership_included_men']   ?? 2;
		$form->membership_included_women = $overrides['membership_included_women'] ?? 1;
		$form->extra_seat_price          = $overrides['extra_seat_price']          ?? 100;
		$form->guest_seat_price          = $overrides['guest_seat_price']          ?? 180;
		$form->max_installments          = $overrides['max_installments']          ?? 12;
		$form->sponsorships              = $overrides['sponsorships']              ?? BMM_Form_Config::default_sponsorships();
		return $form;
	}

	/** Seats keyed by davening name, with any missing davenings set to $fill. */
	private function seats( array $values, int $fill = 0 ): array {
		$seats = array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), $fill );
		return array_merge( $seats, $values );
	}

	// ── normalize_seats ────────────────────────────────────────────────────────

	public function test_normalize_seats_fills_all_six_davenings(): void {
		$out = BMM_Pricing::normalize_seats( [] );
		$this->assertSame( array_keys( BMM_Pricing::DAVENINGS ), array_keys( $out ) );
		$this->assertSame( [ 0, 0, 0, 0, 0, 0 ], array_values( $out ) );
	}

	public function test_normalize_seats_clamps_negatives_and_casts(): void {
		$out = BMM_Pricing::normalize_seats( [ 'rh_night1' => -5, 'rh_day1' => '3' ] );
		$this->assertSame( 0, $out['rh_night1'] );
		$this->assertSame( 3, $out['rh_day1'] );
	}

	public function test_normalize_seats_accepts_indexed_array(): void {
		$out = BMM_Pricing::normalize_seats( [ 4, 0, 0, 0, 0, 2 ] );
		$this->assertSame( 4, $out['rh_night1'] );
		$this->assertSame( 2, $out['yk_day'] );
	}

	// ── seats_for_holiday ────────────────────────────────────────────────────────

	public function test_seats_for_holiday_is_peak_across_that_holidays_davenings(): void {
		$seats = $this->seats( [ 'rh_night1' => 2, 'rh_day1' => 5, 'yk_day' => 3 ] );
		$this->assertSame( 5, BMM_Pricing::seats_for_holiday( $seats, 'rh' ) );
		$this->assertSame( 3, BMM_Pricing::seats_for_holiday( $seats, 'yk' ) );
	}

	public function test_seats_for_holiday_ignores_the_other_holiday(): void {
		// A large YK request must not leak into the RH count and vice-versa.
		$seats = $this->seats( [ 'yk_night' => 9 ] );
		$this->assertSame( 0, BMM_Pricing::seats_for_holiday( $seats, 'rh' ) );
		$this->assertSame( 9, BMM_Pricing::seats_for_holiday( $seats, 'yk' ) );
	}

	public function test_seats_for_holiday_empty_and_unknown_holiday(): void {
		$this->assertSame( 0, BMM_Pricing::seats_for_holiday( [], 'rh' ) );
		$this->assertSame( 0, BMM_Pricing::seats_for_holiday( $this->seats( [ 'rh_day1' => 4 ] ), 'nope' ) );
	}

	public function test_holidays_partition_all_davenings_exactly(): void {
		// Guard against drift: every davening belongs to exactly one holiday
		// group, and the groups introduce no unknown keys.
		$grouped = array_merge( ...array_values( BMM_Pricing::HOLIDAYS ) );
		sort( $grouped );
		$all = array_keys( BMM_Pricing::DAVENINGS );
		sort( $all );
		$this->assertSame( $all, $grouped );
	}

	// ── Membership path ─────────────────────────────────────────────────────────

	public function test_membership_only_no_extra_seats(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'wants_membership' => true,
			'seats_men'        => $this->seats( [ 'rh_day1' => 2 ] ), // == included, no extra
			'seats_women'      => $this->seats( [ 'rh_day1' => 1 ] ),
		] );

		$this->assertSame( 500, $out['membership'] );
		$this->assertSame( 0, $out['extra_men_seats'] );
		$this->assertSame( 0, $out['extra_women_seats'] );
		$this->assertSame( 500, $out['total'] );
	}

	public function test_membership_with_extra_men_and_women(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'wants_membership' => true,
			'seats_men'        => $this->seats( [ 'rh_day1' => 3 ] ), // 3 - 2 included = 1 extra
			'seats_women'      => $this->seats( [ 'yk_day' => 3 ] ),  // 3 - 1 included = 2 extra
		] );

		$this->assertSame( 1, $out['extra_men_count'] );
		$this->assertSame( 2, $out['extra_women_count'] );
		$this->assertSame( 100, $out['extra_men_seats'] );   // 1 * 100
		$this->assertSame( 200, $out['extra_women_seats'] ); // 2 * 100
		$this->assertSame( 800, $out['total'] );             // 500 + 100 + 200
	}

	// ── Guest-seats path ────────────────────────────────────────────────────────

	public function test_guest_seats_without_membership(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'wants_guest_seats' => true,
			'seats_men'         => $this->seats( [ 'rh_day1' => 3 ] ),
			'seats_women'       => $this->seats( [ 'yk_day' => 2 ] ),
		] );

		$this->assertSame( 0, $out['membership'] );
		$this->assertTrue( $out['wants_guest_seats'] );
		$this->assertSame( 3, $out['guest_men_count'] );
		$this->assertSame( 2, $out['guest_women_count'] );
		$this->assertSame( 540, $out['guest_men_seats'] );   // 3 * 180
		$this->assertSame( 360, $out['guest_women_seats'] ); // 2 * 180
		$this->assertSame( 900, $out['total'] );
	}

	public function test_membership_and_guest_are_mutually_exclusive(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'wants_membership'  => true,
			'wants_guest_seats' => true, // must be ignored while membership is on
			'seats_men'         => $this->seats( [ 'rh_day1' => 3 ] ),
		] );

		$this->assertFalse( $out['wants_guest_seats'] );
		$this->assertSame( 0, $out['guest_men_seats'] );
		$this->assertSame( 500, $out['membership'] );
	}

	// ── Horaat Keva path ──────────────────────────────────────────────────────

	public function test_horaat_keva_charges_no_membership_fee_and_includes_one_each(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'has_horaat_keva' => true,
			'seats_men'       => $this->seats( [ 'rh_day1' => 1 ] ), // == 1 included, no extra
			'seats_women'     => $this->seats( [ 'rh_day1' => 1 ] ), // == 1 included, no extra
		] );

		$this->assertTrue( $out['has_horaat_keva'] );
		$this->assertSame( 0, $out['membership'] );
		$this->assertSame( 0, $out['extra_men_seats'] );
		$this->assertSame( 0, $out['extra_women_seats'] );
		$this->assertSame( 0, $out['total'] );
	}

	public function test_horaat_keva_bills_extra_seats_at_member_rate(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'has_horaat_keva' => true,
			'seats_men'       => $this->seats( [ 'rh_day1' => 3 ] ), // 3 - 1 included = 2 extra
			'seats_women'     => $this->seats( [ 'yk_day' => 2 ] ),  // 2 - 1 included = 1 extra
		] );

		$this->assertSame( 2, $out['extra_men_count'] );
		$this->assertSame( 1, $out['extra_women_count'] );
		$this->assertSame( 200, $out['extra_men_seats'] );   // 2 * 100
		$this->assertSame( 100, $out['extra_women_seats'] ); // 1 * 100
		$this->assertSame( 300, $out['total'] );             // 0 membership + 200 + 100
	}

	public function test_membership_takes_priority_over_horaat_keva(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'wants_membership' => true,
			'has_horaat_keva'  => true, // must be ignored while membership is on
			'seats_men'        => $this->seats( [ 'rh_day1' => 2 ] ),
		] );

		$this->assertFalse( $out['has_horaat_keva'] );
		$this->assertSame( 500, $out['membership'] );
	}

	public function test_horaat_keva_takes_priority_over_guest_seats(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'has_horaat_keva'   => true,
			'wants_guest_seats' => true, // must be ignored while Horaat Keva is on
			'seats_men'         => $this->seats( [ 'rh_day1' => 2 ] ), // 1 extra @ member rate
		] );

		$this->assertTrue( $out['has_horaat_keva'] );
		$this->assertFalse( $out['wants_guest_seats'] );
		$this->assertSame( 0, $out['guest_men_seats'] );
		$this->assertSame( 100, $out['extra_men_seats'] ); // 1 extra * 100
	}

	// ── Sponsorships ────────────────────────────────────────────────────────────

	public function test_predefined_sponsorships_sum(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'sponsorship_ids' => [ 'kiddush', 'avos_ubanim' ],
		] );

		$this->assertSame( 650, $out['sponsorships_total'] ); // 350 + 300
		$this->assertSame( 650, $out['total'] );
		$this->assertCount( 2, $out['sponsorships'] );
	}

	public function test_unknown_sponsorship_id_is_ignored(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'sponsorship_ids' => [ 'kiddush', 'does_not_exist' ],
		] );

		$this->assertSame( 350, $out['sponsorships_total'] );
		$this->assertCount( 1, $out['sponsorships'] );
	}

	public function test_other_sponsorship_amount_is_added(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'sponsorship_ids'   => [ 'kiddush' ],
			'sponsorship_other' => 200,
		] );

		$this->assertSame( 550, $out['sponsorships_total'] ); // 350 + 200
		$other = array_values( array_filter( $out['sponsorships'], fn( $s ) => $s['id'] === 'other' ) );
		$this->assertCount( 1, $other );
		$this->assertSame( 200, $other[0]['amount'] );
	}

	public function test_negative_other_sponsorship_is_clamped_to_zero(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'sponsorship_other' => -50,
		] );

		$this->assertSame( 0, $out['sponsorships_total'] );
		$this->assertSame( [], $out['sponsorships'] );
	}

	public function test_disabled_sponsorship_cannot_be_selected(): void {
		$form = $this->makeForm( [
			'sponsorships' => [
				[ 'id' => 'kiddush', 'label' => 'Kiddush Fund', 'amount' => 350, 'enabled' => false ],
			],
		] );
		$out = BMM_Pricing::calculate( $form, [ 'sponsorship_ids' => [ 'kiddush' ] ] );

		$this->assertSame( 0, $out['sponsorships_total'] );
	}

	// ── Combined total ──────────────────────────────────────────────────────────

	public function test_full_breakdown_total_is_sum_of_parts(): void {
		$form = $this->makeForm();
		$out  = BMM_Pricing::calculate( $form, [
			'wants_membership'  => true,
			'seats_men'         => $this->seats( [ 'rh_day1' => 3 ] ), // 1 extra = 100
			'seats_women'       => $this->seats( [ 'yk_day' => 2 ] ),  // 1 extra = 100
			'sponsorship_ids'   => [ 'shalosh_seudos' ],               // 250
			'sponsorship_other' => 75,
		] );

		// 500 + 100 + 100 + 250 + 75
		$this->assertSame( 1025, $out['total'] );
	}
}
