<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Pure-logic tests for the Submissions-list helpers:
 *   - resolve_list_status(): status filter → WP_Query args (defaults to
 *     "completed", supports "all" and the audit-only "unverified").
 *   - completion_is_unverified(): the payment-audit rule for spotting
 *     "completed" submissions that never actually paid.
 */
#[CoversClass( BMM_Submission::class )]
final class SubmissionQueryTest extends TestCase {

	// ── resolve_list_status ─────────────────────────────────────────────────────

	public function test_empty_filter_defaults_to_completed(): void {
		$args = BMM_Submission::resolve_list_status( '' );
		$this->assertSame( 'completed', $args['post_status'] );
		$this->assertArrayNotHasKey( 'meta_query', $args );
	}

	public function test_all_returns_every_status(): void {
		$args = BMM_Submission::resolve_list_status( 'all' );
		$this->assertSame( [ 'bmm_pending', 'completed', 'failed' ], $args['post_status'] );
	}

	public function test_each_concrete_status_passes_through(): void {
		foreach ( [ 'bmm_pending', 'completed', 'failed' ] as $status ) {
			$args = BMM_Submission::resolve_list_status( $status );
			$this->assertSame( $status, $args['post_status'] );
			$this->assertArrayNotHasKey( 'meta_query', $args );
		}
	}

	public function test_unverified_narrows_to_flagged_completed(): void {
		$args = BMM_Submission::resolve_list_status( 'unverified' );
		$this->assertSame( 'completed', $args['post_status'] );
		$this->assertSame(
			[ [ 'key' => '_bmm_sub_payment_unverified', 'value' => 1, 'compare' => '=' ] ],
			$args['meta_query']
		);
	}

	public function test_unknown_status_falls_back_to_all(): void {
		$args = BMM_Submission::resolve_list_status( 'bogus' );
		$this->assertSame( [ 'bmm_pending', 'completed', 'failed' ], $args['post_status'] );
	}

	// ── completion_is_unverified ──────────────────────────────────────────────────

	public function test_paid_completion_with_transaction_id_is_verified(): void {
		$this->assertFalse( BMM_Submission::completion_is_unverified( 500, '123456', '' ) );
	}

	public function test_horaat_keva_completion_is_verified(): void {
		$this->assertFalse( BMM_Submission::completion_is_unverified( 500, '', '98765' ) );
	}

	public function test_owing_completion_without_any_id_is_unverified(): void {
		$this->assertTrue( BMM_Submission::completion_is_unverified( 500, '', '' ) );
		$this->assertTrue( BMM_Submission::completion_is_unverified( 500, '   ', '  ' ) );
	}

	public function test_zero_total_is_never_unverified(): void {
		// Nothing to pay → no transaction expected, so not a false completion.
		$this->assertFalse( BMM_Submission::completion_is_unverified( 0, '', '' ) );
	}
}
