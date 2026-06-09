<?php
defined( 'ABSPATH' ) || exit;

class BMM_Settings {

	private const OPTION_KEY = 'bmm_registration_settings';

	public static function get( string $key, mixed $default = '' ): mixed {
		$settings = get_option( self::OPTION_KEY, [] );
		return $settings[ $key ] ?? $default;
	}

	public static function get_all(): array {
		return get_option( self::OPTION_KEY, [] );
	}

	public static function save( array $data ): void {
		$clean = [
			'mosad'        => sanitize_text_field( $data['mosad'] ?? '' ),
			'api_valid'    => sanitize_text_field( $data['api_valid'] ?? '' ),
			'api_password' => sanitize_text_field( $data['api_password'] ?? '' ),
		];
		update_option( self::OPTION_KEY, $clean );
	}

	public static function register_settings_page(): void {
		add_submenu_page(
			'bmm-registration',
			__( 'Settings', 'bmm-registration' ),
			__( 'Settings', 'bmm-registration' ),
			'manage_options',
			'bmm-settings',
			[ self::class, 'render_settings_page' ]
		);
	}

	public static function render_settings_page(): void {
		if ( isset( $_POST['bmm_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_settings_nonce'] ) ), 'bmm_save_settings' ) ) {
			self::save( wp_unslash( $_POST ) );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'bmm-registration' ) . '</p></div>';
		}

		$settings = self::get_all();
		require BMM_REG_DIR . 'admin/views/settings-page.php';
	}
}
