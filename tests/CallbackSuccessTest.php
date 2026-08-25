<?php
declare( strict_types=1 );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Guards the rule that a submission is only ever marked "completed" for an
 * actually-approved Nedarim payment. Nedarim posts the CallBack URL for
 * declined / errored attempts too, so BMM_Callback_Handler::payment_succeeded()
 * must return false unless the payload carries real evidence of success.
 */
#[CoversClass( BMM_Callback_Handler::class )]
final class CallbackSuccessTest extends TestCase {

	// ── Success ────────────────────────────────────────────────────────────────

	public function test_regular_transaction_with_transaction_id_succeeds(): void {
		$this->assertTrue( BMM_Callback_Handler::payment_succeeded( [
			'TransactionId' => '123456',
			'Confirmation'  => '0012345',
			'Amount'        => '500',
		] ) );
	}

	public function test_horaat_keva_with_keva_id_succeeds(): void {
		$this->assertTrue( BMM_Callback_Handler::payment_succeeded( [
			'KevaId' => '98765',
			'Amount' => '500',
		] ) );
	}

	public function test_explicit_ok_status_succeeds(): void {
		$this->assertTrue( BMM_Callback_Handler::payment_succeeded( [ 'Status' => 'OK' ] ) );
	}

	// ── Failure ────────────────────────────────────────────────────────────────

	public function test_empty_payload_does_not_succeed(): void {
		$this->assertFalse( BMM_Callback_Handler::payment_succeeded( [] ) );
	}

	public function test_zero_transaction_id_does_not_succeed(): void {
		$this->assertFalse( BMM_Callback_Handler::payment_succeeded( [ 'TransactionId' => '0' ] ) );
	}

	public function test_empty_transaction_id_does_not_succeed(): void {
		$this->assertFalse( BMM_Callback_Handler::payment_succeeded( [ 'TransactionId' => '', 'Amount' => '0' ] ) );
	}

	public function test_explicit_error_status_loses_even_with_stale_id(): void {
		// A failure verdict is authoritative even if a leftover id rides along.
		$this->assertFalse( BMM_Callback_Handler::payment_succeeded( [
			'Status'        => 'Error',
			'TransactionId' => '123',
		] ) );
	}

	public function test_declined_status_does_not_succeed(): void {
		$this->assertFalse( BMM_Callback_Handler::payment_succeeded( [ 'Status' => 'Declined' ] ) );
	}
}
