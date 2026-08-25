<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * End-to-end tests for the submissions bulk-action mutation logic against an
 * in-memory post store (see tests/wp-stubs.php). Proves each bulk action —
 * Mark Completed / Pending / Failed and Move to Trash — actually changes the
 * targeted submissions, skips foreign posts, and reports the right count.
 */
#[CoversClass( BMM_Admin::class )]
final class BulkActionHandlerTest extends TestCase {

	protected function setUp(): void {
		__wp_reset_posts();
	}

	// ── resolve_current_bulk_action ──────────────────────────────────────────────

	public function test_resolve_reads_top_then_bottom_control(): void {
		$this->assertSame( 'mark_failed', BMM_Admin::resolve_current_bulk_action( [ 'action' => 'mark_failed' ] ) );
		// Top is "no action" (-1) → fall through to the bottom control.
		$this->assertSame( 'delete', BMM_Admin::resolve_current_bulk_action( [ 'action' => '-1', 'action2' => 'delete' ] ) );
		$this->assertSame( '', BMM_Admin::resolve_current_bulk_action( [ 'action' => '-1', 'action2' => '-1' ] ) );
		$this->assertSame( '', BMM_Admin::resolve_current_bulk_action( [] ) );
	}

	// ── apply_bulk_action: each action ───────────────────────────────────────────

	public function test_mark_failed_sets_all_targeted_to_failed(): void {
		__wp_seed_post( 1, 'bmm_submission', 'completed' );
		__wp_seed_post( 2, 'bmm_submission', 'completed' );

		$count = BMM_Admin::apply_bulk_action( 'mark_failed', [ 1, 2 ] );

		$this->assertSame( 2, $count );
		$this->assertSame( 'failed', __wp_status( 1 ) );
		$this->assertSame( 'failed', __wp_status( 2 ) );
	}

	public function test_mark_completed_and_pending(): void {
		__wp_seed_post( 1, 'bmm_submission', 'failed' );
		__wp_seed_post( 2, 'bmm_submission', 'completed' );

		$this->assertSame( 1, BMM_Admin::apply_bulk_action( 'mark_completed', [ 1 ] ) );
		$this->assertSame( 'completed', __wp_status( 1 ) );

		$this->assertSame( 1, BMM_Admin::apply_bulk_action( 'mark_pending', [ 2 ] ) );
		$this->assertSame( 'bmm_pending', __wp_status( 2 ) );
	}

	public function test_delete_moves_to_trash(): void {
		__wp_seed_post( 5, 'bmm_submission', 'completed' );

		$count = BMM_Admin::apply_bulk_action( 'delete', [ 5 ] );

		$this->assertSame( 1, $count );
		$this->assertSame( 'trash', __wp_status( 5 ) );
	}

	// ── Safety: never touch foreign or missing posts ─────────────────────────────

	public function test_skips_non_submission_and_missing_posts(): void {
		__wp_seed_post( 1, 'bmm_submission', 'completed' );
		__wp_seed_post( 2, 'page', 'publish' ); // not ours — must be skipped

		$count = BMM_Admin::apply_bulk_action( 'mark_failed', [ 1, 2, 999 ] );

		$this->assertSame( 1, $count, 'only the real submission is counted' );
		$this->assertSame( 'failed', __wp_status( 1 ) );
		$this->assertSame( 'publish', __wp_status( 2 ), 'foreign post untouched' );
	}

	public function test_unknown_action_changes_nothing(): void {
		__wp_seed_post( 1, 'bmm_submission', 'completed' );

		$this->assertSame( 0, BMM_Admin::apply_bulk_action( 'bogus', [ 1 ] ) );
		$this->assertSame( 'completed', __wp_status( 1 ) );
	}

	public function test_empty_and_zero_ids_are_ignored(): void {
		__wp_seed_post( 1, 'bmm_submission', 'completed' );
		$this->assertSame( 0, BMM_Admin::apply_bulk_action( 'mark_failed', [ 0, -3 ] ) );
		$this->assertSame( 'completed', __wp_status( 1 ) );
	}

	// ── Revert path reuses the same proven logic ─────────────────────────────────

	public function test_revert_reuses_mark_failed(): void {
		// Simulates handle_revert_unverified() calling apply_bulk_action('mark_failed', $flagged).
		__wp_seed_post( 10, 'bmm_submission', 'completed' );
		__wp_seed_post( 11, 'bmm_submission', 'completed' );

		$count = BMM_Admin::apply_bulk_action( 'mark_failed', [ 10, 11 ] );

		$this->assertSame( 2, $count );
		$this->assertSame( 'failed', __wp_status( 10 ) );
		$this->assertSame( 'failed', __wp_status( 11 ) );
	}
}
