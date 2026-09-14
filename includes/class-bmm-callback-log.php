<?php
defined( 'ABSPATH' ) || exit;

/**
 * A small rolling log of inbound Nedarim callback attempts — every request that
 * hits /nedarim-callback, whether it completed a submission, was rejected (bad
 * IP / token), or represented a declined payment.
 *
 * Nedarim sends each completion callback exactly once with no retry, so a lost
 * or wrongly-rejected callback silently leaves a paid submission "pending". This
 * log makes that visible: for a pending-but-paid submission you can see whether
 * a callback ever arrived and why it was not applied.
 */
class BMM_Callback_Log {

	private const OPTION = 'bmm_callback_log';
	private const MAX    = 200;

	/** Append one attempt (newest first, capped). */
	public static function record( array $entry ): void {
		$entry['time'] = current_time( 'mysql' );

		$log = self::all();
		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX ) {
			$log = array_slice( $log, 0, self::MAX );
		}
		// autoload = false: this can grow and is only read on its admin page.
		update_option( self::OPTION, $log, false );
	}

	/** @return array[] newest first */
	public static function all(): array {
		$log = get_option( self::OPTION, [] );
		return is_array( $log ) ? $log : [];
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
