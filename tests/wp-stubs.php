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

// ── Post-meta store (for the submission-edit / re-pricing round trip) ──────────

$GLOBALS['__wp_meta'] = [];

/** Reset the in-memory meta store between tests. */
function __wp_reset_meta(): void {
	$GLOBALS['__wp_meta'] = [];
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value ) {
		// Mirror WordPress: update_metadata() unslashes the stored value, which is
		// exactly what encode_json()'s wp_slash() is designed to survive.
		$GLOBALS['__wp_meta'][ (int) $id ][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = true ) {
		return $GLOBALS['__wp_meta'][ (int) $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $id, $key ) {
		unset( $GLOBALS['__wp_meta'][ (int) $id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return is_string( $value ) ? addslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return '2026-01-01T00:00:00+00:00';
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( ...$args ) {}
}
