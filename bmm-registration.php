<?php
/**
 * Plugin Name: BMM Registration
 * Plugin URI:  https://bmm.org.il
 * Description: Yomim Noraim seat reservations and annual membership registration with Nedarim Plus payment.
 * Version:     1.0.5
 * Author:      BMM
 * Text Domain: bmm-registration
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'BMM_REG_VERSION', '1.0.5' );

// Plugin update checker — checks GitHub for new versions.
// If the repo is private, define BMM_GITHUB_TOKEN in wp-config.php.
require_once plugin_dir_path( __FILE__ ) . 'vendor/plugin-update-checker/plugin-update-checker.php';
$bmm_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/meirgarfinkel/bmm-registration',
	__FILE__,
	'bmm-registration'
);
$bmm_updater->setBranch( 'tevi' );
unset( $bmm_updater );
define( 'BMM_REG_FILE', __FILE__ );
define( 'BMM_REG_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMM_REG_URL', plugin_dir_url( __FILE__ ) );

// Load all classes upfront so they are available for the activation hook,
// which fires before plugins_loaded.
require_once BMM_REG_DIR . 'includes/class-bmm-settings.php';
require_once BMM_REG_DIR . 'includes/class-bmm-form-config.php';
require_once BMM_REG_DIR . 'includes/class-bmm-pricing.php';
require_once BMM_REG_DIR . 'includes/class-bmm-submission.php';
require_once BMM_REG_DIR . 'includes/class-bmm-callback-handler.php';
require_once BMM_REG_DIR . 'includes/class-bmm-csv-export.php';
require_once BMM_REG_DIR . 'includes/class-bmm-shortcode.php';
require_once BMM_REG_DIR . 'includes/class-bmm-post-types.php';
require_once BMM_REG_DIR . 'rest-api/class-bmm-rest-price.php';
require_once BMM_REG_DIR . 'rest-api/class-bmm-rest-submit.php';
require_once BMM_REG_DIR . 'rest-api/class-bmm-rest-callback.php';
require_once BMM_REG_DIR . 'public/class-bmm-form-renderer.php';

if ( is_admin() ) {
	require_once BMM_REG_DIR . 'admin/class-bmm-admin.php';
	require_once BMM_REG_DIR . 'admin/class-bmm-form-editor.php';
	require_once BMM_REG_DIR . 'admin/class-bmm-submissions-list.php';
}

require_once BMM_REG_DIR . 'includes/class-bmm-plugin.php';

register_activation_hook( __FILE__, [ 'BMM_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BMM_Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'BMM_Plugin', 'get_instance' ] );
