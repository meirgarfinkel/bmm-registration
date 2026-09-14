<?php
defined( 'ABSPATH' ) || exit;

class BMM_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_post_bmm_export_csv', [ 'BMM_CSV_Export', 'handle_export_request' ] );
		add_action( 'admin_post_bmm_update_submission_status', [ self::class, 'handle_status_update' ] );
		add_action( 'admin_post_bmm_update_submission', [ self::class, 'handle_submission_update' ] );
		add_action( 'admin_post_bmm_audit_payments', [ self::class, 'handle_payment_audit' ] );
		add_action( 'admin_post_bmm_revert_unverified', [ self::class, 'handle_revert_unverified' ] );
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

		add_submenu_page(
			'bmm-registration',
			__( 'Reconcile Payments', 'bmm-registration' ),
			__( 'Reconcile Payments', 'bmm-registration' ),
			'manage_options',
			'bmm-reconcile',
			[ self::class, 'render_reconcile_page' ]
		);

		add_submenu_page(
			'bmm-registration',
			__( 'Payment Callbacks', 'bmm-registration' ),
			__( 'Payment Callbacks', 'bmm-registration' ),
			'manage_options',
			'bmm-callback-log',
			[ self::class, 'render_callback_log_page' ]
		);

		BMM_Settings::register_settings_page();
	}

	public static function render_callback_log_page(): void {
		$entries = BMM_Callback_Log::all();
		require BMM_REG_DIR . 'admin/views/callback-log-page.php';
	}

	/**
	 * Reconcile Payments: pull Nedarim's cleared-transaction history and propose
	 * matches to pending submissions (confirm-first). Handles both the "find
	 * matches" and the "complete selected" steps, then hands data to the view.
	 */
	public static function render_reconcile_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'bmm-registration' ) );
		}

		$mosad        = (string) BMM_Settings::get( 'mosad' );
		$api_password = (string) BMM_Settings::get( 'api_password' );
		$error        = '';
		$matches      = [];
		$completed    = null;
		$did_search   = false;

		// Step 2 — complete the confirmed matches.
		if (
			isset( $_POST['bmm_reconcile_apply'], $_POST['bmm_reconcile_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_reconcile_nonce'] ) ), 'bmm_reconcile' )
		) {
			$completed = self::apply_reconcile_matches(
				(array) ( $_POST['confirm_ids'] ?? [] ),
				wp_unslash( (array) ( $_POST['txn'] ?? [] ) )
			);
		}

		// Step 1 — find matches.
		if (
			isset( $_POST['bmm_reconcile_find'], $_POST['bmm_reconcile_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_reconcile_nonce'] ) ), 'bmm_reconcile' )
		) {
			$did_search   = true;
			$transactions = BMM_Reconcile::fetch_transactions( $mosad, $api_password );
			if ( is_wp_error( $transactions ) ) {
				$error = $transactions->get_error_message();
			} else {
				$matches = self::build_reconcile_matches( $transactions );
			}
		}

		$has_creds = ( $mosad !== '' && $api_password !== '' );
		require BMM_REG_DIR . 'admin/views/reconcile-page.php';
	}

	/** Match currently-pending submissions against the fetched transactions. */
	private static function build_reconcile_matches( array $transactions ): array {
		// Exclude transactions already recorded on a completed submission, so a
		// person's earlier (already-applied) payment isn't proposed again.
		$used_txn = [];
		foreach ( get_posts( [ 'post_type' => 'bmm_submission', 'post_status' => 'completed', 'posts_per_page' => -1, 'fields' => 'ids' ] ) as $cid ) {
			$t = trim( (string) get_post_meta( (int) $cid, '_bmm_sub_nedarim_transaction_id', true ) );
			if ( $t !== '' ) {
				$used_txn[ $t ] = true;
			}
		}
		$transactions = array_values( array_filter( $transactions, static function ( $t ) use ( $used_txn ) {
			$id = trim( (string) ( $t['TransactionId'] ?? '' ) );
			return $id === '' || ! isset( $used_txn[ $id ] );
		} ) );

		$pendings = [];
		foreach ( get_posts( [ 'post_type' => 'bmm_submission', 'post_status' => 'bmm_pending', 'posts_per_page' => -1, 'fields' => 'ids' ] ) as $pid ) {
			$pid        = (int) $pid;
			$pendings[] = [
				'id'    => $pid,
				'name'  => trim( get_post( $pid )->post_title ),
				'total' => (int) get_post_meta( $pid, '_bmm_sub_price_total', true ),
				'phone' => (string) get_post_meta( $pid, '_bmm_sub_phone', true ),
				'zeout' => (string) get_post_meta( $pid, '_bmm_sub_zeout', true ),
				'email' => (string) get_post_meta( $pid, '_bmm_sub_email', true ),
			];
		}

		$matches = BMM_Reconcile::match( $pendings, $transactions );

		// Attach the pending's display fields for the confirmation table.
		$by_id = array_column( $pendings, null, 'id' );
		foreach ( $matches as &$m ) {
			$m['pending'] = $by_id[ $m['submission_id'] ] ?? [];
		}
		return $matches;
	}

	/**
	 * Complete the submissions the admin confirmed. Each confirm entry is the
	 * matched transaction data (submitted from the confirmation table).
	 */
	private static function apply_reconcile_matches( array $ids, array $txn_map ): int {
		$count = 0;
		foreach ( $ids as $submission_id ) {
			$submission_id = (int) $submission_id;
			$txn           = $txn_map[ $submission_id ] ?? null;
			$post          = $submission_id ? get_post( $submission_id ) : null;
			if ( ! is_array( $txn ) || ! $post || $post->post_type !== 'bmm_submission' || $post->post_status === 'completed' ) {
				continue;
			}
			$payload = [
				'TransactionId' => sanitize_text_field( $txn['txn_id'] ?? '' ),
				'Confirmation'  => sanitize_text_field( $txn['confirmation'] ?? '' ),
				'LastNum'       => sanitize_text_field( $txn['last4'] ?? '' ),
				'Amount'        => sanitize_text_field( $txn['amount'] ?? '' ),
			];
			if ( $payload['TransactionId'] === '' ) {
				continue; // never complete without a real transaction id
			}
			BMM_Submission::complete( $submission_id, $payload, false );
			update_post_meta( $submission_id, '_bmm_sub_reconciled', 1 );
			$count++;
		}
		return $count;
	}

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

	/**
	 * Resolve the chosen bulk action from a request. WP_List_Table puts it in
	 * 'action' (top controls) or 'action2' (bottom controls); '-1' means none.
	 * Returns '' when no bulk action was selected. Pure — unit-testable.
	 */
	public static function resolve_current_bulk_action( array $request ): string {
		foreach ( [ 'action', 'action2' ] as $key ) {
			if ( isset( $request[ $key ] ) && $request[ $key ] !== '-1' && $request[ $key ] !== '' ) {
				return sanitize_key( (string) $request[ $key ] );
			}
		}
		return '';
	}

	/**
	 * Apply a bulk action to a set of submission IDs and return how many were
	 * changed. Skips anything that is not a bmm_submission. Extracted from the
	 * request/redirect wrapper so the actual mutation logic is unit-testable.
	 */
	public static function apply_bulk_action( string $action, array $ids ): int {
		if ( $action !== 'delete' && self::bulk_action_new_status( $action ) === null ) {
			return 0; // not one of our actions
		}
		$new_status = self::bulk_action_new_status( $action );

		$count = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
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
		return $count;
	}

	public static function process_submissions_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = self::resolve_current_bulk_action( wp_unslash( $_REQUEST ) );
		if ( $action === '' ) {
			return; // no bulk action selected (e.g. the Filter button)
		}
		if ( $action !== 'delete' && self::bulk_action_new_status( $action ) === null ) {
			return; // not one of our bulk actions
		}

		// Nonce added by WP_List_Table::display() as bulk-{plural}.
		check_admin_referer( 'bulk-submissions' );

		$ids = isset( $_REQUEST['submission_ids'] )
			? array_map( 'intval', (array) wp_unslash( $_REQUEST['submission_ids'] ) )
			: [];

		$count = self::apply_bulk_action( $action, $ids );

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
	 * Save an admin edit of a submission's registrant data (name, contact,
	 * Hebrew names, membership options, per-davening seats, notes). Recomputes
	 * the price breakdown from the edited seats/membership so the record stays
	 * consistent; payment/transaction fields are left untouched.
	 */
	public static function handle_submission_update(): void {
		if (
			! isset( $_POST['bmm_edit_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_edit_nonce'] ) ), 'bmm_edit_submission' ) ||
			! current_user_can( 'manage_options' )
		) {
			wp_die( esc_html__( 'Unauthorized', 'bmm-registration' ) );
		}

		$submission_id = (int) ( $_POST['submission_id'] ?? 0 );
		$post          = $submission_id ? get_post( $submission_id ) : null;
		if ( ! $post || $post->post_type !== 'bmm_submission' ) {
			wp_die( esc_html__( 'Submission not found.', 'bmm-registration' ) );
		}

		$raw = wp_unslash( $_POST );

		// Children names arrive as a newline-separated textarea.
		$children = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $raw['children_hebrew_names'] ?? '' ) ) ) );

		BMM_Submission::update_fields( $submission_id, [
			'first_name'        => $raw['first_name']       ?? '',
			'last_name'         => $raw['last_name']        ?? '',
			'email'             => $raw['email']            ?? '',
			'phone'             => $raw['phone']            ?? '',
			'city'              => $raw['city']             ?? '',
			'address'           => $raw['address']          ?? '',
			'zeout'             => $raw['zeout']            ?? '',
			'hebrew_name'       => $raw['hebrew_name']      ?? '',
			'tribe'             => $raw['tribe']            ?? 'yisrael',
			'wife_hebrew_name'  => $raw['wife_hebrew_name'] ?? '',
			'children_hebrew_names' => array_values( $children ),
			'wants_membership'  => ! empty( $raw['wants_membership'] ),
			'has_horaat_keva'   => ! empty( $raw['has_horaat_keva'] ),
			'wants_guest_seats' => ! empty( $raw['wants_guest_seats'] ),
			'seats_men'         => (array) ( $raw['seats_men'] ?? [] ),
			'seats_women'       => (array) ( $raw['seats_women'] ?? [] ),
			'notes'             => $raw['notes'] ?? '',
		] );

		// Keep the price breakdown consistent with the edited seats/membership.
		if ( $post->post_parent ) {
			try {
				BMM_Submission::recalculate_pricing( $submission_id, new BMM_Form_Config( $post->post_parent ) );
			} catch ( \Exception $e ) {
				// Form deleted — leave the stored pricing as-is.
			}
		}

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'bmm-submissions', 'submission_id' => $submission_id, 'bmm_saved' => 1 ],
			admin_url( 'admin.php' )
		) );
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
				'id'          => $id,
				'form'        => $post ? (int) $post->post_parent : 0,
				'total'       => (int) get_post_meta( $id, '_bmm_sub_price_total', true ),
				'paid'        => self::record_looks_paid( $id ),
				'horaat_keva' => ! empty( get_post_meta( $id, '_bmm_sub_has_horaat_keva', true ) ),
				'email'       => strtolower( trim( (string) get_post_meta( $id, '_bmm_sub_email', true ) ) ),
				'phone'       => preg_replace( '/\D/', '', (string) get_post_meta( $id, '_bmm_sub_phone', true ) ),
			];
		}

		// Reason per record.
		$reasons = [];
		foreach ( $records as $id => $r ) {
			if ( $r['total'] > 0 && ! $r['paid'] ) {
				// Signal 1: owes money but doesn't look paid.
				$reasons[ $id ] = 'no_payment';
			} elseif ( $r['total'] <= 0 && ! $r['horaat_keva'] ) {
				// Signal 1b: completed for ₪0 but NOT a legitimate Horaat-Keva
				// free order — the payment step was skipped in error and no
				// money was collected. (Legit free = Horaat Keva, which is fine.)
				$reasons[ $id ] = 'zero_no_payment';
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
	 * One-click cleanup: mark every audit-flagged (unverified) completed
	 * submission as Failed. Uses the reliable admin-post POST flow (not the
	 * WP_List_Table bulk mechanism), so it is a dependable path to correct the
	 * historical false completions. Run "Audit Payments" first to populate flags.
	 */
	public static function handle_revert_unverified(): void {
		if (
			! isset( $_POST['bmm_revert_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_revert_nonce'] ) ), 'bmm_revert_unverified' ) ||
			! current_user_can( 'manage_options' )
		) {
			wp_die( esc_html__( 'Unauthorized', 'bmm-registration' ) );
		}

		$ids = get_posts( [
			'post_type'      => 'bmm_submission',
			'post_status'    => 'completed',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_bmm_sub_payment_unverified', 'value' => 1, 'compare' => '=' ],
			],
		] );

		$count = self::apply_bulk_action( 'mark_failed', array_map( 'intval', (array) $ids ) );

		$redirect = add_query_arg(
			[
				'page'           => 'bmm-submissions',
				'payment_status' => 'failed',
				'bmm_reverted'   => $count,
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
