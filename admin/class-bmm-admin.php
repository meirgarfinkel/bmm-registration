<?php
defined( 'ABSPATH' ) || exit;

class BMM_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_post_bmm_export_csv', [ 'BMM_CSV_Export', 'handle_export_request' ] );
		add_action( 'admin_post_bmm_update_submission_status', [ self::class, 'handle_status_update' ] );
		add_action( 'admin_post_bmm_audit_payments', [ self::class, 'handle_payment_audit' ] );
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

		$submissions_hook = add_submenu_page(
			'bmm-registration',
			__( 'Submissions', 'bmm-registration' ),
			__( 'Submissions', 'bmm-registration' ),
			'manage_options',
			'bmm-submissions',
			[ self::class, 'render_submissions_page' ]
		);

		// Process bulk actions on the submissions list. The 'load-{hook}' action
		// fires before any admin HTML is output, so wp_safe_redirect() works here
		// (it would not inside the page-render callback, where output has begun).
		if ( $submissions_hook ) {
			add_action( "load-{$submissions_hook}", [ self::class, 'process_submissions_bulk_action' ] );
		}

		BMM_Settings::register_settings_page();
	}

	/**
	 * Handle "Delete" / "Mark as …" bulk actions from the submissions list.
	 * Runs on the page's load hook (before output) so it can redirect cleanly.
	 */
	/**
	 * Map a submissions bulk-action key to the post_status it sets, or null if
	 * it is not one of our status actions. Pure, so it can be unit-tested.
	 */
	public static function bulk_action_new_status( string $action ): ?string {
		return [
			'mark_completed' => 'completed',
			'mark_pending'   => 'bmm_pending',
			'mark_failed'    => 'failed',
		][ $action ] ?? null;
	}

	public static function process_submissions_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// WP_List_Table exposes the chosen action in 'action' (top) or 'action2'
		// (bottom). '-1' means "no action selected".
		$action = '-1';
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] !== '-1' ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] !== '-1' ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}

		$new_status = self::bulk_action_new_status( $action );
		if ( $action !== 'delete' && $new_status === null ) {
			return; // not one of our bulk actions
		}

		// Nonce added by WP_List_Table::display() as bulk-{plural}.
		check_admin_referer( 'bulk-submissions' );

		$ids = isset( $_REQUEST['submission_ids'] )
			? array_map( 'intval', (array) wp_unslash( $_REQUEST['submission_ids'] ) )
			: [];
		$ids = array_filter( $ids );

		$count = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== 'bmm_submission' ) {
				continue;
			}
			if ( $action === 'delete' ) {
				if ( wp_trash_post( $id ) ) {
					$count++;
				}
			} else {
				wp_update_post( [ 'ID' => $id, 'post_status' => $new_status ] );
				$count++;
			}
		}

		// Redirect back to the list (dropping the action/nonce/ids params so a
		// refresh does not re-run the action), preserving the active filters.
		$redirect = add_query_arg(
			[
				'page'           => 'bmm-submissions',
				'form_id'        => isset( $_REQUEST['form_id'] ) ? (int) $_REQUEST['form_id'] : null,
				'payment_status' => isset( $_REQUEST['payment_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['payment_status'] ) ) : null,
				'paged'          => isset( $_REQUEST['paged'] ) ? (int) $_REQUEST['paged'] : null,
				'bmm_bulk'       => $action,
				'bmm_count'      => $count,
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render_forms_list(): void {
		$forms = get_posts( [
			'post_type'      => 'bmm_reg_form',
			'post_status'    => [ 'draft', 'publish', 'published', 'archived' ],
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

	/**
	 * One-time (repeatable) audit that flags submissions marked "completed" that
	 * carry no evidence of an actual payment — i.e. a positive locked total but
	 * neither a Nedarim transaction id nor a Horaat Keva id. These are the false
	 * completions created before the callback-success gate was added.
	 *
	 * This only FLAGS (meta _bmm_sub_payment_unverified) — it never changes a
	 * submission's status — so a legitimate manual completion is never clobbered.
	 * Admins review the flagged rows (via the "Completed — unverified" filter)
	 * and correct them by hand. Re-running clears stale flags.
	 */
	public static function handle_payment_audit(): void {
		if (
			! isset( $_POST['bmm_audit_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_audit_nonce'] ) ), 'bmm_audit_payments' ) ||
			! current_user_can( 'manage_options' )
		) {
			wp_die( esc_html__( 'Unauthorized', 'bmm-registration' ) );
		}

		$completed = get_posts( [
			'post_type'      => 'bmm_submission',
			'post_status'    => 'completed',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		// Gather each completed submission's payment facts once.
		$records = [];
		foreach ( $completed as $id ) {
			$id      = (int) $id;
			$post    = get_post( $id );
			$records[ $id ] = [
				'id'    => $id,
				'form'  => $post ? (int) $post->post_parent : 0,
				'total' => (int) get_post_meta( $id, '_bmm_sub_price_total', true ),
				'paid'  => self::record_looks_paid( $id ),
				'email' => strtolower( trim( (string) get_post_meta( $id, '_bmm_sub_email', true ) ) ),
				'phone' => preg_replace( '/\D/', '', (string) get_post_meta( $id, '_bmm_sub_phone', true ) ),
			];
		}

		// Reason per record. Signal 1: owes money but doesn't look paid.
		$reasons = [];
		foreach ( $records as $id => $r ) {
			if ( $r['total'] > 0 && ! $r['paid'] ) {
				$reasons[ $id ] = 'no_payment';
			}
		}

		// Signal 2: same-registrant duplicates. Group completed submissions by
		// (form + email|phone); when a group has a genuinely-paid member, every
		// other member is a duplicate of a single payment and is flagged too.
		$groups = [];
		foreach ( $records as $r ) {
			$who = $r['email'] !== '' ? 'e:' . $r['email'] : ( $r['phone'] !== '' ? 'p:' . $r['phone'] : '' );
			if ( $who === '' ) {
				continue; // no identity to group on — judged individually only
			}
			$groups[ $r['form'] . '|' . $who ][] = $r['id'];
		}
		foreach ( $groups as $ids ) {
			if ( count( $ids ) < 2 ) {
				continue;
			}
			$paid_ids = array_values( array_filter( $ids, static fn( $i ) => $records[ $i ]['paid'] ) );
			if ( ! $paid_ids ) {
				continue; // none paid → already handled by signal 1 (or nothing owed)
			}
			// Keep the earliest paid submission; flag the rest of the group.
			$keep = min( $paid_ids );
			foreach ( $ids as $i ) {
				if ( $i !== $keep && ! isset( $reasons[ $i ] ) ) {
					$reasons[ $i ] = 'duplicate';
				}
			}
		}

		$flagged = 0;
		foreach ( $records as $id => $r ) {
			if ( isset( $reasons[ $id ] ) ) {
				update_post_meta( $id, '_bmm_sub_payment_unverified', 1 );
				update_post_meta( $id, '_bmm_sub_payment_unverified_reason', $reasons[ $id ] );
				$flagged++;
			} else {
				delete_post_meta( $id, '_bmm_sub_payment_unverified' );
				delete_post_meta( $id, '_bmm_sub_payment_unverified_reason' );
			}
		}

		$redirect = add_query_arg(
			[
				'page'              => 'bmm-submissions',
				'bmm_audit'         => 1,
				'bmm_audit_flagged' => $flagged,
			],
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Whether a completed submission looks paid — authoritatively, by re-running
	 * its stored Nedarim callback through the live success gate (falling back to
	 * a stored transaction/Keva id when no raw callback was captured).
	 */
	public static function record_looks_paid( int $submission_id ): bool {
		$raw     = (string) get_post_meta( $submission_id, '_bmm_sub_nedarim_raw_callback', true );
		$payload = $raw !== '' ? json_decode( $raw, true ) : null;

		return BMM_Submission::record_looks_paid(
			is_array( $payload ) ? $payload : null,
			(string) get_post_meta( $submission_id, '_bmm_sub_nedarim_transaction_id', true ),
			(string) get_post_meta( $submission_id, '_bmm_sub_nedarim_keva_id', true )
		);
	}

	/**
	 * A "completed" submission is unverified when it owes money (locked total
	 * > 0) yet does not look paid. (Duplicate detection lives in the audit, which
	 * needs cross-submission context.)
	 */
	public static function is_payment_unverified( int $submission_id ): bool {
		$raw     = (string) get_post_meta( $submission_id, '_bmm_sub_nedarim_raw_callback', true );
		$payload = $raw !== '' ? json_decode( $raw, true ) : null;

		return BMM_Submission::completion_is_unverified(
			(int) get_post_meta( $submission_id, '_bmm_sub_price_total', true ),
			(string) get_post_meta( $submission_id, '_bmm_sub_nedarim_transaction_id', true ),
			(string) get_post_meta( $submission_id, '_bmm_sub_nedarim_keva_id', true ),
			is_array( $payload ) ? $payload : null
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'bmm' ) === false && strpos( $hook, 'bmm_reg_form' ) === false ) {
			return;
		}
		wp_enqueue_style( 'bmm-admin', BMM_REG_URL . 'assets/css/bmm-admin.css', [], BMM_REG_VERSION );
		wp_enqueue_script( 'bmm-admin', BMM_REG_URL . 'assets/js/bmm-admin.js', [ 'jquery' ], BMM_REG_VERSION, true );
	}
}
