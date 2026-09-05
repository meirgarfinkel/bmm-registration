<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Admin submission editing: update_fields() must persist the edited registrant
 * data (name, seats, membership), and recalculate_pricing() must re-derive the
 * price breakdown from the edited data so the record stays consistent.
 * Exercised against the in-memory meta store (tests/wp-stubs.php).
 */
#[CoversClass( BMM_Submission::class )]
final class SubmissionEditTest extends TestCase {

	protected function setUp(): void {
		__wp_reset_meta();
	}

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

	public function test_update_fields_persists_name_and_seats(): void {
		BMM_Submission::update_fields( 42, [
			'first_name'  => 'Moshe',
			'last_name'   => 'Cohen',
			'email'       => 'moshe@example.com',
			'tribe'       => 'kohen',
			'seats_men'   => $this->seats( [ 'rh_day1' => 4 ] ),
			'seats_women' => $this->seats( [ 'yk_day' => 2 ] ),
		] );

		$meta = BMM_Submission::get_meta( 42 );
		$this->assertSame( 'Moshe', $meta['first_name'] );
		$this->assertSame( 'Cohen', $meta['last_name'] );
		$this->assertSame( 'kohen', $meta['tribe'] );
		$this->assertSame( 4, $meta['seats_men']['rh_day1'] );
		$this->assertSame( 2, $meta['seats_women']['yk_day'] );
	}

	public function test_invalid_tribe_falls_back_to_yisrael(): void {
		BMM_Submission::update_fields( 42, [ 'first_name' => 'A', 'last_name' => 'B', 'tribe' => 'bogus' ] );
		$this->assertSame( 'yisrael', BMM_Submission::get_meta( 42 )['tribe'] );
	}

	public function test_recalculate_pricing_updates_total_from_edited_seats(): void {
		// Membership + 2 extra men (4 requested − 2 included) + 1 extra woman
		// (2 − 1 included) at ₪100 each → 500 + 200 + 100 = 800.
		BMM_Submission::update_fields( 7, [
			'first_name'       => 'Test',
			'last_name'        => 'Member',
			'wants_membership' => true,
			'seats_men'        => $this->seats( [ 'rh_day1' => 4 ] ),
			'seats_women'      => $this->seats( [ 'yk_day' => 2 ] ),
		] );

		$pricing = BMM_Submission::recalculate_pricing( 7, $this->makeForm() );

		$this->assertSame( 800, $pricing['total'] );
		$this->assertSame( '800', (string) BMM_Submission::get_meta( 7 )['price_total'] );
		$this->assertSame( '200', (string) BMM_Submission::get_meta( 7 )['price_extra_men'] );
	}

	public function test_horaat_keva_edit_reprices_to_zero(): void {
		// Editing a member down to Horaat-Keva with no extra seats → ₪0 order.
		BMM_Submission::update_fields( 9, [
			'first_name'      => 'Free',
			'last_name'       => 'Member',
			'has_horaat_keva' => true,
			'seats_men'       => $this->seats( [] ),
			'seats_women'     => $this->seats( [] ),
		] );

		$pricing = BMM_Submission::recalculate_pricing( 9, $this->makeForm() );
		$this->assertSame( 0, $pricing['total'] );
	}
}
