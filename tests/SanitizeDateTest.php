<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers BMM_Submission::sanitize_date(), the strict Y-m-d guard used for the
 * Kiddush Fund date. It is private, so we reach it by reflection.
 */
#[CoversClass( BMM_Submission::class )]
final class SanitizeDateTest extends TestCase {

	private function sanitize( $value ): string {
		$method = new ReflectionMethod( BMM_Submission::class, 'sanitize_date' );
		$method->setAccessible( true );
		return $method->invoke( null, $value );
	}

	public static function cases(): array {
		return [
			'valid date'            => [ '2026-09-20', '2026-09-20' ],
			'valid leap day'        => [ '2024-02-29', '2024-02-29' ],
			'trims surrounding ws'  => [ "  2026-09-20\n", '2026-09-20' ],
			'empty string'          => [ '', '' ],
			'non-leap Feb 29'       => [ '2026-02-29', '' ],
			'impossible day'        => [ '2026-02-30', '' ],
			'month out of range'    => [ '2026-13-01', '' ],
			'unpadded (not date-input format)' => [ '2026-9-2', '' ],
			'slashes not dashes'    => [ '2026/09/20', '' ],
			'free text'             => [ 'tomorrow', '' ],
			'datetime string'       => [ '2026-09-20T10:00', '' ],
		];
	}

	#[DataProvider( 'cases' )]
	public function test_sanitize_date( string $input, string $expected ): void {
		$this->assertSame( $expected, $this->sanitize( $input ) );
	}
}
