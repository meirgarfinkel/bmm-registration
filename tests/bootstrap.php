<?php
/**
 * PHPUnit bootstrap for the BMM Registration unit tests.
 *
 * These are true unit tests: they exercise the plugin's pure logic without a
 * running WordPress. The plugin class files guard themselves with
 * `defined( 'ABSPATH' ) || exit;`, so we define ABSPATH here and stub the few
 * WordPress functions the classes under test touch at call time.
 */

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// Minimal WordPress shims for the code paths exercised by the unit tests.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

require_once __DIR__ . '/wp-stubs.php';

$root = dirname( __DIR__ );
require_once $root . '/includes/class-bmm-form-config.php';
require_once $root . '/includes/class-bmm-pricing.php';
require_once $root . '/includes/class-bmm-submission.php';
require_once $root . '/includes/class-bmm-callback-handler.php';
require_once $root . '/includes/class-bmm-csv-export.php';
require_once $root . '/admin/class-bmm-admin.php';
