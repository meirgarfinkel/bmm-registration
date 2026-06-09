<?php
/**
 * Plugin Name: BMM Registration
 * Plugin URI:  https://bmm.org.il
 * Description: Yomim Noraim seat reservations and annual membership registration with Nedarim Plus payment.
 * Version:     1.0.0
 * Author:      BMM
 * Text Domain: bmm-registration
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'BMM_REG_VERSION', '1.0.0' );
define( 'BMM_REG_FILE', __FILE__ );
define( 'BMM_REG_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMM_REG_URL', plugin_dir_url( __FILE__ ) );

require_once BMM_REG_DIR . 'includes/class-bmm-plugin.php';

register_activation_hook( __FILE__, [ 'BMM_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BMM_Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'BMM_Plugin', 'get_instance' ] );
