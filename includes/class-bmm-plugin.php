<?php
defined( 'ABSPATH' ) || exit;

class BMM_Plugin {

	private static ?BMM_Plugin $instance = null;

	public static function get_instance(): BMM_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		require_once BMM_REG_DIR . 'includes/class-bmm-post-types.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-settings.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-form-config.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-pricing.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-submission.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-callback-handler.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-csv-export.php';
		require_once BMM_REG_DIR . 'includes/class-bmm-shortcode.php';
		require_once BMM_REG_DIR . 'rest-api/class-bmm-rest-price.php';
		require_once BMM_REG_DIR . 'rest-api/class-bmm-rest-submit.php';
		require_once BMM_REG_DIR . 'rest-api/class-bmm-rest-callback.php';

		if ( is_admin() ) {
			require_once BMM_REG_DIR . 'admin/class-bmm-admin.php';
			require_once BMM_REG_DIR . 'admin/class-bmm-form-editor.php';
			require_once BMM_REG_DIR . 'admin/class-bmm-submissions-list.php';
		}

		require_once BMM_REG_DIR . 'public/class-bmm-form-renderer.php';
	}

	private function init_hooks(): void {
		add_action( 'init', [ 'BMM_Post_Types', 'register' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		BMM_Shortcode::init();

		if ( is_admin() ) {
			BMM_Admin::init();
			BMM_Form_Editor::init();
		}
	}

	public function register_rest_routes(): void {
		( new BMM_REST_Price() )->register_routes();
		( new BMM_REST_Submit() )->register_routes();
		( new BMM_REST_Callback() )->register_routes();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'bmm-registration',
			false,
			dirname( plugin_basename( BMM_REG_FILE ) ) . '/languages'
		);
	}

	public static function activate(): void {
		BMM_Post_Types::register();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
