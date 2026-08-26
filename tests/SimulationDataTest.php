<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * BMM_Submission::build_simulation_data(): the admin "Simulate Nedarim callback"
 * diagnostic must carry the real form entry (name + seats, including the
 * "same for all davenings" per-davening inputs) into the test submission, not
 * throw it away for empty dummy data (the reported "lost the seats/name" bug).
 */
#[CoversClass( BMM_Submission::class )]
final class SimulationDataTest extends TestCase {

	public function test_passes_through_submitted_name_and_seats(): void {
		$men   = [ 'rh_night1' => 2, 'rh_day1' => 2, 'yk_night' => 2, 'yk_day' => 2 ];
		$women = [ 'rh_night1' => 1, 'rh_day1' => 1, 'yk_night' => 1, 'yk_day' => 1 ];

		$data = BMM_Submission::build_simulation_data( [
			'first_name'  => 'Moshe',
			'last_name'   => 'Cohen',
			'email'       => 'moshe@example.com',
			'seats_men'   => $men,
			'seats_women' => $women,
		] );

		$this->assertSame( 'Moshe', $data['first_name'] );
		$this->assertSame( 'Cohen', $data['last_name'] );
		$this->assertSame( 'moshe@example.com', $data['email'] );
		$this->assertSame( $men, $data['seats_men'] );
		$this->assertSame( $women, $data['seats_women'] );
	}

	public function test_fills_dummy_defaults_when_fields_missing(): void {
		$data = BMM_Submission::build_simulation_data( [] );

		$this->assertSame( 'Test', $data['first_name'] );
		$this->assertSame( 'Simulation', $data['last_name'] );
		$this->assertSame( 'simulation@example.com', $data['email'] );
		$this->assertSame( 'yisrael', $data['tribe'] );
		$this->assertSame( [], $data['seats_men'] );
		$this->assertSame( [], $data['seats_women'] );
	}

	public function test_membership_defaults_true_only_when_no_seats(): void {
		// Bare simulation (no seats) → membership on, so there is a total.
		$this->assertTrue( BMM_Submission::build_simulation_data( [] )['wants_membership'] );

		// Seats entered but membership not mentioned → do not force membership.
		$withSeats = BMM_Submission::build_simulation_data( [ 'seats_men' => [ 'rh_day1' => 3 ] ] );
		$this->assertFalse( $withSeats['wants_membership'] );

		// Explicit choice always wins.
		$this->assertTrue( BMM_Submission::build_simulation_data( [ 'wants_membership' => 1, 'seats_men' => [ 'rh_day1' => 3 ] ] )['wants_membership'] );
		$this->assertFalse( BMM_Submission::build_simulation_data( [ 'wants_membership' => 0 ] )['wants_membership'] );
	}

	public function test_membership_choices_are_booleans(): void {
		$data = BMM_Submission::build_simulation_data( [ 'has_horaat_keva' => 1, 'wants_guest_seats' => 0 ] );
		$this->assertTrue( $data['has_horaat_keva'] );
		$this->assertFalse( $data['wants_guest_seats'] );
	}
}
