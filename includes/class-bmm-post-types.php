<?php
defined( 'ABSPATH' ) || exit;

class BMM_Post_Types {

	public static function register(): void {
		self::register_form_cpt();
		self::register_submission_cpt();
		self::register_custom_statuses();
		self::add_query_var();
	}

	private static function register_form_cpt(): void {
		register_post_type( 'bmm_reg_form', [
			'labels'              => [
				'name'               => __( 'Registration Forms', 'bmm-registration' ),
				'singular_name'      => __( 'Registration Form', 'bmm-registration' ),
				'add_new'            => __( 'Add New Form', 'bmm-registration' ),
				'add_new_item'       => __( 'Add New Registration Form', 'bmm-registration' ),
				'edit_item'          => __( 'Edit Registration Form', 'bmm-registration' ),
				'new_item'           => __( 'New Registration Form', 'bmm-registration' ),
				'view_item'          => __( 'View Form', 'bmm-registration' ),
				'search_items'       => __( 'Search Forms', 'bmm-registration' ),
				'not_found'          => __( 'No forms found', 'bmm-registration' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}

	private static function register_submission_cpt(): void {
		register_post_type( 'bmm_submission', [
			'labels'              => [
				'name'               => __( 'Submissions', 'bmm-registration' ),
				'singular_name'      => __( 'Submission', 'bmm-registration' ),
				'view_item'          => __( 'View Submission', 'bmm-registration' ),
				'search_items'       => __( 'Search Submissions', 'bmm-registration' ),
				'not_found'          => __( 'No submissions found', 'bmm-registration' ),
			],
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		] );
	}

	private static function register_custom_statuses(): void {
		// Form statuses
		register_post_status( 'published', [
			'label'                     => __( 'Published', 'bmm-registration' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Published <span class="count">(%s)</span>', 'Published <span class="count">(%s)</span>', 'bmm-registration' ),
		] );

		register_post_status( 'archived', [
			'label'                     => __( 'Archived', 'bmm-registration' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'bmm-registration' ),
		] );

		// Submission statuses
		register_post_status( 'pending', [
			'label'                     => __( 'Pending Payment', 'bmm-registration' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'bmm-registration' ),
		] );

		register_post_status( 'completed', [
			'label'                     => __( 'Completed', 'bmm-registration' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>', 'bmm-registration' ),
		] );

		register_post_status( 'failed', [
			'label'                     => __( 'Failed', 'bmm-registration' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Failed <span class="count">(%s)</span>', 'Failed <span class="count">(%s)</span>', 'bmm-registration' ),
		] );
	}

	private static function add_query_var(): void {
		add_filter( 'query_vars', function ( array $vars ): array {
			$vars[] = 'bmm_form';
			return $vars;
		} );

		add_action( 'template_redirect', function (): void {
			$slug = get_query_var( 'bmm_form' );
			if ( ! $slug ) {
				return;
			}

			$form_post = BMM_Form_Config::get_by_slug( $slug );
			if ( ! $form_post ) {
				return;
			}

			// Render the registration form page
			add_filter( 'the_content', function () use ( $form_post ): string {
				return BMM_Form_Renderer::render( $form_post->ID );
			} );
		} );
	}
}
