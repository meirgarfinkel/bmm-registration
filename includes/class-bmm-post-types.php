<?php
defined( 'ABSPATH' ) || exit;

class BMM_Post_Types {

	/**
	 * Bump this whenever the rewrite rules change. The auto-flush below
	 * compares it against a stored option and re-flushes once on mismatch,
	 * so plugin updates (which do NOT fire the activation hook) still take
	 * effect without a manual Settings → Permalinks save.
	 */
	const REWRITE_VERSION = '2';

	public static function register(): void {
		self::register_form_cpt();
		self::register_submission_cpt();
		self::register_custom_statuses();
		self::add_rewrite_rule();   // must be called directly during 'init', not via add_action('init')
		self::add_query_var();
		self::intercept_publish_status();
		self::register_permalink_filter();
		self::register_nav_menu_status_fix();
		self::maybe_flush_rewrite_rules();
	}

	/**
	 * Self-healing rewrite flush. Runs late on 'init' (after add_rewrite_rule()
	 * has registered our rule) and flushes exactly once per REWRITE_VERSION.
	 */
	private static function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'bmm_rewrite_version' ) === self::REWRITE_VERSION ) {
			return;
		}
		flush_rewrite_rules( false ); // soft flush — updates the rewrite_rules option only
		update_option( 'bmm_rewrite_version', self::REWRITE_VERSION );
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
	 * Make published forms appear in the Appearance → Menus meta box.
	 *
	 * The nav-menu meta box (wp_nav_menu_item_post_type_meta_box) builds its
	 * post query with suppress_filters = true, so pre_get_posts never fires.
	 * It also applies _wp_nav_menu_meta_box_object(), which hardcodes
	 * _default_query => ['post_status' => 'publish'] for custom post types.
	 * Our forms use the custom 'published' status, so that query returns zero
	 * rows ("No items"). The only effective hook is nav_menu_meta_box_object:
	 * override _default_query to accept both 'publish' and 'published'.
	 */
	private static function register_nav_menu_status_fix(): void {
		add_filter( 'nav_menu_meta_box_object', function ( $object ) {
			if ( isset( $object->name ) && $object->name === 'bmm_reg_form' ) {
				$object->_default_query = [
					'post_status' => [ 'publish', 'published' ],
					'orderby'     => 'title',
					'order'       => 'ASC',
				];
			}
			return $object;
		}, 11 ); // after core's _wp_nav_menu_meta_box_object (priority 10)
	}

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

	/**
	 * Remap WordPress's built-in 'publish' status to our custom 'published'
	 * for bmm_reg_form posts. This fires whenever an admin uses the native WP
	 * Publish button instead of our custom status controls.
	 */
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
		add_filter( 'query_vars', function ( array $vars ): array {
			$vars[] = 'bmm_form';
			return $vars;
		} );

		// Path-based routing that does NOT depend on the rewrite rule being
		// flushed. If the request path is /register/{slug}/, set the bmm_form
		// query var directly. This is the authoritative router; the rewrite
		// rule is kept only as a belt-and-suspenders for pretty-permalink envs.
		add_filter( 'request', function ( array $qv ): array {
			if ( ! empty( $qv['bmm_form'] ) ) {
				return $qv; // rewrite rule already resolved it
			}
			$path = isset( $_SERVER['REQUEST_URI'] )
				? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
				: '';
			if ( $path && preg_match( '#(?:^|/)register/([^/]+)/?$#i', $path, $m ) ) {
				// Replace the query vars entirely so WP does not build a 404 query.
				return [ 'bmm_form' => urldecode( $m[1] ) ];
			}
			return $qv;
		} );

		// Enqueue form assets on the proper hook so CSS lands in <head>.
		// (template_include fires after wp_head, so enqueuing inside render()
		// alone would drop the stylesheet from the header.)
		add_action( 'wp_enqueue_scripts', function (): void {
			$slug = get_query_var( 'bmm_form' );
			if ( ! $slug ) {
				return;
			}
			$form_post = BMM_Form_Config::get_by_slug( $slug );
			if ( ! $form_post ) {
				return;
			}
			try {
				BMM_Form_Renderer::enqueue_assets( new BMM_Form_Config( $form_post->ID ) );
			} catch ( \Exception $e ) {
				// Form config invalid — render() will handle the user-facing message.
			}
		} );

		add_filter( 'template_include', function ( string $template ): string {
			$slug = get_query_var( 'bmm_form' );
			if ( ! $slug ) {
				return $template;
			}

			$form_post = BMM_Form_Config::get_by_slug( $slug );
			if ( ! $form_post ) {
				// Route is active but no form resolved. Don't fail silently —
				// surface the reason to admins so the cause is unambiguous.
				if ( current_user_can( 'manage_options' ) ) {
					global $bmm_diagnostic_message;
					$bmm_diagnostic_message = sprintf(
						'BMM diagnostic: no registration form found for slug "%s". '
						. 'Check that the form exists and its slug matches the URL.',
						esc_html( $slug )
					);
					global $wp_query;
					$wp_query->is_404 = false;
					status_header( 200 );
					return BMM_REG_DIR . 'public/views/form-page.php';
				}
				return $template;
			}

			// Build a fake $post AND fully populate the main query so that any
			// theme template's loop (have_posts()/the_post()) actually runs and
			// calls the_content() — that's where we inject the form. Without
			// populating $wp_query->posts, the loop body never executes and the
			// page renders theme chrome with no content.
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
				'post_date'      => current_time( 'mysql' ),
				'post_date_gmt'  => current_time( 'mysql', true ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'comment_count'  => 0,
				'filter'         => 'raw',
			] );

			// Replace the (empty, 404-ing) main query with our single fake post.
			$wp_query->posts             = [ $post ];
			$wp_query->post              = $post;
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = 0;
			$wp_query->post_count        = 1;
			$wp_query->found_posts       = 1;
			$wp_query->max_num_pages     = 1;
			$wp_query->current_post      = -1;
			$wp_query->is_page     = true;
			$wp_query->is_singular = true;
			$wp_query->is_single   = false;
			$wp_query->is_home     = false;
			$wp_query->is_archive  = false;
			$wp_query->is_search   = false;
			$wp_query->is_404      = false;
			setup_postdata( $post );
			status_header( 200 );

			// Inject the form HTML wherever the theme calls the_content().
			// High priority so it wins over other the_content filters.
			add_filter( 'the_content', function ( $content ) use ( $form_post ) {
				return BMM_Form_Renderer::render( $form_post->ID );
			}, 99 );

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
