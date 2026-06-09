<?php
defined( 'ABSPATH' ) || exit;

class BMM_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_post_bmm_export_csv', [ 'BMM_CSV_Export', 'handle_export_request' ] );
		add_action( 'admin_post_bmm_update_submission_status', [ self::class, 'handle_status_update' ] );
	}

	public static function register_menus(): void {
		add_menu_page(
			__( 'BMM Registration', 'bmm-registration' ),
			__( 'BMM Registration', 'bmm-registration' ),
			'manage_options',
			'bmm-registration',
			[ self::class, 'render_forms_list' ],
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'bmm-registration',
			__( 'Registration Forms', 'bmm-registration' ),
			__( 'Forms', 'bmm-registration' ),
			'manage_options',
			'bmm-registration',
			[ self::class, 'render_forms_list' ]
		);

		add_submenu_page(
			'bmm-registration',
			__( 'Submissions', 'bmm-registration' ),
			__( 'Submissions', 'bmm-registration' ),
			'manage_options',
			'bmm-submissions',
			[ self::class, 'render_submissions_page' ]
		);

		BMM_Settings::register_settings_page();
	}

	public static function render_forms_list(): void {
		$forms = get_posts( [
			'post_type'      => 'bmm_reg_form',
			'post_status'    => [ 'draft', 'published', 'archived' ],
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		require BMM_REG_DIR . 'admin/views/forms-list.php';
	}

	public static function render_submissions_page(): void {
		if ( isset( $_GET['submission_id'] ) ) {
			$submission_id = (int) $_GET['submission_id'];
			$post          = get_post( $submission_id );
			if ( $post && $post->post_type === 'bmm_submission' ) {
				$meta = BMM_Submission::get_meta( $submission_id );
				$form_config = null;
				if ( $post->post_parent ) {
					try { $form_config = new BMM_Form_Config( $post->post_parent ); } catch ( \Exception $e ) {}
				}
				require BMM_REG_DIR . 'admin/views/submission-detail.php';
				return;
			}
		}

		$list_table = new BMM_Submissions_List();
		$list_table->prepare_items();
		require BMM_REG_DIR . 'admin/views/submissions-list-page.php';
	}

	public static function handle_status_update(): void {
		if (
			! isset( $_POST['bmm_status_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_status_nonce'] ) ), 'bmm_update_status' ) ||
			! current_user_can( 'manage_options' )
		) {
			wp_die( esc_html__( 'Unauthorized', 'bmm-registration' ) );
		}

		$submission_id = (int) ( $_POST['submission_id'] ?? 0 );
		$new_status    = sanitize_key( $_POST['new_status'] ?? '' );

		if ( $submission_id && in_array( $new_status, [ 'bmm_pending', 'completed', 'failed' ], true ) ) {
			wp_update_post( [ 'ID' => $submission_id, 'post_status' => $new_status ] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bmm-submissions&submission_id=' . $submission_id ) );
		exit;
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'bmm' ) === false && strpos( $hook, 'bmm_reg_form' ) === false ) {
			return;
		}
		wp_enqueue_style( 'bmm-admin', BMM_REG_URL . 'assets/css/bmm-admin.css', [], BMM_REG_VERSION );
		wp_enqueue_script( 'bmm-admin', BMM_REG_URL . 'assets/js/bmm-admin.js', [ 'jquery' ], BMM_REG_VERSION, true );
	}
}
