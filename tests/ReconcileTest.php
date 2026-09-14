<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The reconciliation matcher: pending submissions ↔ Nedarim cleared transactions.
 * Money-sensitive, so the pure logic is tested — amount plus a contact field is
 * required, each transaction is used once, and the strongest identity wins.
 */
#[CoversClass( BMM_Reconcile::class )]
final class ReconcileTest extends TestCase {

	private function txn( array $o ): array {
		return array_merge( [
			'Amount' => '0', 'Phone' => '', 'Zeout' => '', 'Mail' => '',
			'TransactionId' => '', 'Confirmation' => '', 'LastNum' => '', 'TransactionTime' => '',
		], $o );
	}

	public function test_matches_on_amount_plus_phone(): void {
		$pendings = [ [ 'id' => 1, 'total' => 500, 'phone' => '050-123-4567', 'zeout' => '', 'email' => '' ] ];
		$txns     = [ $this->txn( [ 'Amount' => '500', 'Phone' => '0501234567', 'TransactionId' => 'T1', 'Confirmation' => 'C1' ] ) ];

		$m = BMM_Reconcile::match( $pendings, $txns );

		$this->assertCount( 1, $m );
		$this->assertSame( 1, $m[0]['submission_id'] );
		$this->assertSame( 'T1', $m[0]['txn']['txn_id'] );
		$this->assertContains( 'phone', $m[0]['reasons'] );
	}

	public function test_amount_alone_is_not_a_match(): void {
		$pendings = [ [ 'id' => 1, 'total' => 500, 'phone' => '0501234567', 'zeout' => '', 'email' => '' ] ];
		// Same amount, but no contact field matches.
		$txns     = [ $this->txn( [ 'Amount' => '500', 'Phone' => '0999999999', 'TransactionId' => 'T1' ] ) ];

		$this->assertSame( [], BMM_Reconcile::match( $pendings, $txns ) );
	}

	public function test_wrong_amount_never_matches(): void {
		$pendings = [ [ 'id' => 1, 'total' => 500, 'phone' => '0501234567', 'zeout' => '', 'email' => '' ] ];
		$txns     = [ $this->txn( [ 'Amount' => '450', 'Phone' => '0501234567', 'TransactionId' => 'T1' ] ) ];

		$this->assertSame( [], BMM_Reconcile::match( $pendings, $txns ) );
	}

	public function test_each_transaction_used_at_most_once(): void {
		// Two pendings, same amount + same phone, but only ONE transaction.
		$pendings = [
			[ 'id' => 1, 'total' => 500, 'phone' => '0501234567', 'zeout' => '', 'email' => '' ],
			[ 'id' => 2, 'total' => 500, 'phone' => '0501234567', 'zeout' => '', 'email' => '' ],
		];
		$txns = [ $this->txn( [ 'Amount' => '500', 'Phone' => '0501234567', 'TransactionId' => 'T1' ] ) ];

		$m = BMM_Reconcile::match( $pendings, $txns );
		$this->assertCount( 1, $m, 'one transaction can only settle one submission' );
	}

	public function test_stronger_identity_match_is_preferred(): void {
		$pendings = [ [ 'id' => 1, 'total' => 500, 'phone' => '0501234567', 'zeout' => '9', 'email' => 'a@x.com' ] ];
		$txns = [
			$this->txn( [ 'Amount' => '500', 'Phone' => '0501234567', 'TransactionId' => 'WEAK' ] ),
			$this->txn( [ 'Amount' => '500', 'Phone' => '0501234567', 'Zeout' => '9', 'Mail' => 'a@x.com', 'TransactionId' => 'STRONG' ] ),
		];

		$m = BMM_Reconcile::match( $pendings, $txns );
		$this->assertCount( 1, $m );
		$this->assertSame( 'STRONG', $m[0]['txn']['txn_id'] );
		$this->assertCount( 3, $m[0]['reasons'] );
	}

	public function test_email_match_is_case_insensitive(): void {
		$pendings = [ [ 'id' => 1, 'total' => 500, 'phone' => '', 'zeout' => '', 'email' => 'Moshe@Example.com' ] ];
		$txns     = [ $this->txn( [ 'Amount' => '500', 'Mail' => 'moshe@example.com', 'TransactionId' => 'T1' ] ) ];

		$m = BMM_Reconcile::match( $pendings, $txns );
		$this->assertCount( 1, $m );
	}

	// ── extract_transactions (defensive response parsing) ────────────────────────

	public function test_extract_from_bare_list(): void {
		$rows = [ [ 'TransactionId' => 'T1' ], [ 'TransactionId' => 'T2' ] ];
		$this->assertSame( $rows, BMM_Reconcile::extract_transactions( $rows ) );
	}

	public function test_extract_from_wrapped_object(): void {
		$rows    = [ [ 'TransactionId' => 'T1' ] ];
		$wrapped = [ 'Result' => 'OK', 'Data' => $rows ];
		$this->assertSame( $rows, BMM_Reconcile::extract_transactions( $wrapped ) );
	}

	public function test_extract_from_empty_is_empty(): void {
		$this->assertSame( [], BMM_Reconcile::extract_transactions( [] ) );
		$this->assertSame( [], BMM_Reconcile::extract_transactions( [ 'Result' => 'OK' ] ) );
	}
}
