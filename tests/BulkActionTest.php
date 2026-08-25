<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The submissions bulk-action → post_status mapping. (The recent bulk-action
 * outage was an HTML form-nesting bug in the admin view, not this mapping; this
 * pins the mapping so "Mark as Failed" et al. resolve to the right statuses.)
 */
#[CoversClass( BMM_Admin::class )]
final class BulkActionTest extends TestCase {

	public function test_mark_failed_maps_to_failed(): void {
		$this->assertSame( 'failed', BMM_Admin::bulk_action_new_status( 'mark_failed' ) );
	}

	public function test_mark_completed_and_pending_map_correctly(): void {
		$this->assertSame( 'completed', BMM_Admin::bulk_action_new_status( 'mark_completed' ) );
		$this->assertSame( 'bmm_pending', BMM_Admin::bulk_action_new_status( 'mark_pending' ) );
	}

	public function test_delete_and_unknown_actions_have_no_status(): void {
		// 'delete' is handled separately (trash), not via a status change.
		$this->assertNull( BMM_Admin::bulk_action_new_status( 'delete' ) );
		$this->assertNull( BMM_Admin::bulk_action_new_status( '-1' ) );
		$this->assertNull( BMM_Admin::bulk_action_new_status( 'bogus' ) );
	}
}
