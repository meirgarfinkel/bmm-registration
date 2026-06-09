<?php
defined( 'ABSPATH' ) || exit;

class BMM_Post_Types {

	public static function register(): void {
		self::register_form_cpt();
		self::register_submission_cpt();
		self::register_custom_statuses();
		self::add_query_var();
		self::intercept_publish_status();
		self::register_permalink_filter();
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
			'show_in_nav_menus'   => true,   // lets published forms appear in Appearance → Menus
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
		register_post_status( 'bmm_pending', [
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

	/**
	 * Remap WordPress's built-in 'publish' status to our custom 'published'
	 * for bmm_reg_form posts. This fires whenever an admin uses the native WP
	 * Publish button instead of our custom status controls.
	 */
	/**
	 * Override the permalink for bmm_reg_form posts so WordPress menus,
	 * admin columns, and any get_permalink() call return our pretty URL.
	 */
	public static function register_permalink_filter(): void {
		add_filter( 'post_type_link', function ( string $url, \WP_Post $post ): string {
			if ( $post->post_type !== 'bmm_reg_form' ) {
				return $url;
			}
			if ( $post->post_name ) {
				return home_url( '/register/' . $post->post_name . '/' );
			}
			// Unslugged draft — keep query-string fallback
			return add_query_arg( 'bmm_form', $post->ID, home_url( '/' ) );
		}, 10, 2 );
	}

	private static function intercept_publish_status(): void {
		add_action( 'transition_post_status', function ( string $new, string $old, \WP_Post $post ): void {
			if ( $post->post_type !== 'bmm_reg_form' || $new !== 'publish' ) {
				return;
			}
			// Use a direct DB write to avoid re-triggering this same hook.
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				[ 'post_status' => 'published' ],
				[ 'ID' => $post->ID ],
				[ '%s' ],
				[ '%d' ]
			);
			clean_post_cache( $post->ID );
		}, 10, 3 );
	}

	/**
	 * Pretty-URL rewrite rule: /register/{slug}/ → ?bmm_form={slug}
	 * Gives forms a clean path that WordPress menus and share links accept.
	 * The query-var path (?bmm_form=) continues to work alongside it.
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^register/([^/]+)/?$',
			'index.php?bmm_form=$1',
			'top'
		);
	}

	private static function add_query_var(): void {
		// Register the rewrite rule on 'init' (same action as register()).
		add_action( 'init', [ self::class, 'add_rewrite_rule' ] );

		add_filter( 'query_vars', function ( array $vars ): array {
			$vars[] = 'bmm_form';
			return $vars;
		} );

		add_filter( 'template_include', function ( string $template ): string {
			$slug = get_query_var( 'bmm_form' );
			if ( ! $slug ) {
				return $template;
			}

			$form_post = BMM_Form_Config::get_by_slug( $slug );
			if ( ! $form_post ) {
				return $template;
			}

			// Build a fake $post so theme templates that call the_content() work.
			// Without this, templates render a blog loop or a blank content area.
			global $post, $wp_query;
			$post = new \WP_Post( (object) [
				'ID'             => 0,
				'post_title'     => $form_post->post_title,
				'post_content'   => '',
				'post_excerpt'   => '',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_name'      => $form_post->post_name,
				'post_author'    => 0,
				'post_date'      => '',
				'post_date_gmt'  => '',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'filter'         => 'raw',
			] );
			setup_postdata( $post );

			// Tell WP this is a singular page so is_page() etc. return correctly.
			$wp_query->is_page     = true;
			$wp_query->is_singular = true;
			$wp_query->is_home     = false;
			$wp_query->is_archive  = false;
			$wp_query->queried_object = $post;

			// Inject the form HTML wherever the theme calls the_content().
			add_filter( 'the_content', function () use ( $form_post ): string {
				return BMM_Form_Renderer::render( $form_post->ID );
			} );

			// Suppress unwanted output (comments, post nav, etc.).
			add_filter( 'comments_open',   '__return_false' );
			add_filter( 'pings_open',      '__return_false' );
			add_filter( 'get_the_excerpt', '__return_empty_string' );

			// Resolve the chosen theme template, falling back to our own wrapper.
			try {
				$config          = new BMM_Form_Config( $form_post->ID );
				$chosen_filename = $config->page_template;
			} catch ( \Exception $e ) {
				$chosen_filename = '';
			}

			if ( $chosen_filename ) {
				$theme_path = get_theme_file_path( $chosen_filename );
				if ( file_exists( $theme_path ) ) {
					return $theme_path;
				}
			}

			// No template chosen — use the plugin's own minimal wrapper.
			global $bmm_current_form_id;
			$bmm_current_form_id = $form_post->ID;
			return BMM_REG_DIR . 'public/views/form-page.php';
		} );
	}
}
