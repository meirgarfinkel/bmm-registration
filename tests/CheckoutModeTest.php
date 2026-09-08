<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The money-critical checkout rule: an order may only skip the payment step
 * when it is a genuine ₪0 Horaat-Keva order. A paid order — and, crucially, an
 * order that comes to ₪0 for the WRONG reason (seats entered without choosing a
 * paid option) — must never complete for free.
 *
 * These tie the real price calculation to BMM_Submission::checkout_mode(), the
 * decision the submit endpoint makes. They exist because a regression here let
 * paying registrants complete for ₪0 with no payment screen.
 */
#[CoversClass( BMM_Submission::class )]
final class CheckoutModeTest extends TestCase {

	private function makeForm(): BMM_Form_Config {
		$form = ( new ReflectionClass( BMM_Form_Config::class ) )->newInstanceWithoutConstructor();
		$form->membership_price          = 500;
		$form->membership_included_men   = 2;
		$form->membership_included_women = 1;
		$form->extra_seat_price          = 100;
		$form->guest_seat_price          = 180;
		$form->max_installments          = 12;
		$form->sponsorships              = BMM_Form_Config::default_sponsorships();
		return $form;
	}

	private function seats( array $values ): array {
		return array_merge( array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), 0 ), $values );
	}

	/** Run the real pricing then the checkout decision, as the endpoint does. */
	private function mode( array $data ): string {
		$pricing = BMM_Pricing::calculate( $this->makeForm(), $data );
		return BMM_Submission::checkout_mode( $data, (int) $pricing['total'] );
	}

	// ── Must charge (reach the payment screen) ───────────────────────────────────

	public function test_membership_with_extra_seats_must_charge(): void {
		$this->assertSame( 'charge', $this->mode( [
			'wants_membership' => true,
			'seats_men'        => $this->seats( [ 'rh_day1' => 4 ] ), // 2 extra
		] ) );
	}

	public function test_guest_seats_must_charge(): void {
		$this->assertSame( 'charge', $this->mode( [
			'wants_guest_seats' => true,
			'seats_men'         => $this->seats( [ 'rh_day1' => 2 ] ),
		] ) );
	}

	public function test_horaat_keva_with_extra_seats_must_charge(): void {
		$this->assertSame( 'charge', $this->mode( [
			'has_horaat_keva' => true,
			'seats_men'       => $this->seats( [ 'rh_day1' => 3 ] ), // 2 beyond the 1 included
		] ) );
	}

	// ── The regression: ₪0 for the WRONG reason must be rejected ─────────────────

	public function test_seats_without_a_paid_mode_is_invalid_not_free(): void {
		// The reported bug: a registrant added seats but selected no paid option,
		// so pricing came to ₪0. This must be rejected, never completed free.
		$data = [
			'seats_men'   => $this->seats( [ 'rh_day1' => 4 ] ),
			'seats_women' => $this->seats( [ 'yk_day' => 2 ] ),
		];
		$pricing = BMM_Pricing::calculate( $this->makeForm(), $data );
		$this->assertSame( 0, $pricing['total'], 'seats alone are not charged' );
		$this->assertSame( 'invalid', BMM_Submission::checkout_mode( $data, (int) $pricing['total'] ) );
	}

	public function test_empty_order_is_invalid(): void {
		$this->assertSame( 'invalid', $this->mode( [] ) );
	}

	// ── The one legitimate free path ─────────────────────────────────────────────

	public function test_horaat_keva_with_no_extra_seats_is_free(): void {
		$this->assertSame( 'free', $this->mode( [
			'has_horaat_keva' => true,
			'seats_men'       => $this->seats( [] ),
			'seats_women'     => $this->seats( [] ),
		] ) );
	}

	// ── Unit-level guards on the decision itself ─────────────────────────────────

	public function test_checkout_mode_positive_total_always_charges(): void {
		$this->assertSame( 'charge', BMM_Submission::checkout_mode( [], 1 ) );
		$this->assertSame( 'charge', BMM_Submission::checkout_mode( [ 'has_horaat_keva' => true ], 250 ) );
	}

	public function test_checkout_mode_zero_total_needs_horaat_keva_for_free(): void {
		$this->assertSame( 'free', BMM_Submission::checkout_mode( [ 'has_horaat_keva' => true ], 0 ) );
		$this->assertSame( 'invalid', BMM_Submission::checkout_mode( [ 'wants_membership' => true ], 0 ) );
		$this->assertSame( 'invalid', BMM_Submission::checkout_mode( [], 0 ) );
	}
}
