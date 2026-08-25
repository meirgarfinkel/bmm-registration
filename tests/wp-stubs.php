<?php
/**
 * Minimal in-memory WordPress shims for exercising admin mutation logic
 * (bulk actions, revert) without a running WordPress. Only the functions the
 * code under test calls are provided; each is guarded so real WP wins if loaded.
 */

declare( strict_types=1 );

$GLOBALS['__wp_posts'] = [];

/** Reset the in-memory post store between tests. */
function __wp_reset_posts(): void {
	$GLOBALS['__wp_posts'] = [];
}

/** Seed a fake post into the store. */
function __wp_seed_post( int $id, string $type = 'bmm_submission', string $status = 'completed' ): void {
	$GLOBALS['__wp_posts'][ $id ] = (object) [
		'ID'          => $id,
		'post_type'   => $type,
		'post_status' => $status,
	];
}

/** Current status of a seeded post (test assertion helper). */
function __wp_status( int $id ): ?string {
	return $GLOBALS['__wp_posts'][ $id ]->post_status ?? null;
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		return $GLOBALS['__wp_posts'][ (int) $id ] ?? null;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( array $data ) {
		$id = (int) ( $data['ID'] ?? 0 );
		if ( $id && isset( $GLOBALS['__wp_posts'][ $id ] ) ) {
			if ( isset( $data['post_status'] ) ) {
				$GLOBALS['__wp_posts'][ $id ]->post_status = $data['post_status'];
			}
			return $id;
		}
		return 0;
	}
}

if ( ! function_exists( 'wp_trash_post' ) ) {
	function wp_trash_post( $id ) {
		$id = (int) $id;
		if ( isset( $GLOBALS['__wp_posts'][ $id ] ) ) {
			$GLOBALS['__wp_posts'][ $id ]->post_status = 'trash';
			return $GLOBALS['__wp_posts'][ $id ];
		}
		return false;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
