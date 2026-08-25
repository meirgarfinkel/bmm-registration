<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Guards the CSV export row builder: every data row must have exactly as many
 * columns as the header (a misalignment silently shifts every field in the
 * spreadsheet), and the per-holiday seat aggregates must land in their columns.
 */
#[CoversClass( BMM_CSV_Export::class )]
final class CsvExportTest extends TestCase {

	/** A fully-populated meta array as BMM_Submission::get_meta() would return. */
	private function meta( array $overrides = [] ): array {
		$zero_seats = array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), 0 );
		return array_merge( [
			'first_name' => 'Moshe', 'last_name' => 'Cohen', 'email' => 'm@example.com',
			'phone' => '0500000000', 'city' => 'BB', 'address' => 'St 1', 'zeout' => '123',
			'hebrew_name' => 'משה', 'tribe' => 'kohen', 'wife_hebrew_name' => 'שרה',
			'children_hebrew_names' => [ 'יוסי', 'רבקה' ],
			'wants_membership' => 1, 'has_horaat_keva' => 0,
			'seats_men' => $zero_seats, 'seats_women' => $zero_seats,
			'kiddush_date' => '', 'kiddush_dedication' => '', 'notes' => '',
			'payment_type' => 'Ragil', 'tashlumim' => 1,
			'price_membership' => 500, 'price_extra_men' => 0, 'price_extra_women' => 0,
			'price_sponsorships' => 0, 'price_total' => 500,
			'nedarim_transaction_id' => 'TXN1', 'nedarim_keva_id' => '',
			'nedarim_confirmation' => 'OK1', 'nedarim_last_num' => '4242',
			'payment_completed_at' => '2026-08-01T10:00:00+00:00',
		], $overrides );
	}

	/** get_headers() and build_row() are private/public static; reach headers via reflection. */
	private function headers(): array {
		$m = new ReflectionMethod( BMM_CSV_Export::class, 'get_headers' );
		$m->setAccessible( true );
		return $m->invoke( null );
	}

	public function test_row_column_count_matches_header_count(): void {
		$row = BMM_CSV_Export::build_row( 7, '2026-08-01 10:00:00', 'completed', 'Form A', $this->meta(), [ 'Kiddush Fund' ] );
		$this->assertSame(
			count( $this->headers() ),
			count( $row ),
			'CSV row must have exactly as many columns as the header'
		);
	}

	public function test_per_holiday_seat_aggregates_are_in_the_row(): void {
		$men   = array_merge( array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), 0 ), [ 'rh_day1' => 4, 'yk_day' => 2 ] );
		$women = array_merge( array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), 0 ), [ 'rh_night1' => 1, 'yk_night' => 3 ] );
		$row   = BMM_CSV_Export::build_row( 7, '2026-08-01', 'completed', 'Form A', $this->meta( [
			'seats_men' => $men, 'seats_women' => $women,
		] ), [] );

		// Locate the aggregates by matching the header labels to row offsets.
		$headers = $this->headers();
		$idx     = static fn( string $label ) => array_search( $label, $headers, true );

		$this->assertSame( 4, $row[ $idx( "Men's Seats - RH" ) ] );
		$this->assertSame( 1, $row[ $idx( "Women's Seats - RH" ) ] );
		$this->assertSame( 2, $row[ $idx( "Men's Seats - YK" ) ] );
		$this->assertSame( 3, $row[ $idx( "Women's Seats - YK" ) ] );
	}

	public function test_horaat_keva_and_membership_flags_render_yes_no(): void {
		$headers = $this->headers();
		$idx     = static fn( string $label ) => array_search( $label, $headers, true );

		$row = BMM_CSV_Export::build_row( 1, 'd', 'completed', 'F', $this->meta( [
			'wants_membership' => 0, 'has_horaat_keva' => 1,
		] ), [] );

		$this->assertSame( 'No',  $row[ $idx( 'Membership Purchased' ) ] );
		$this->assertSame( 'Yes', $row[ $idx( 'Horaat Keva (separate)' ) ] );
	}
}
