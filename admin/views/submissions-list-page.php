<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bmm-submissions-wrap">
	<?php
	$current_form   = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
	$current_status = ! empty( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : 'completed';
	$admin_post     = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Submissions', 'bmm-registration' ); ?></h1>

	<?php // Actions, WordPress-native "page-title-action" buttons next to the title. ?>
	<form method="post" action="<?php echo $admin_post; ?>" class="bmm-title-action">
		<?php wp_nonce_field( 'bmm_export_csv', 'bmm_export_nonce' ); ?>
		<input type="hidden" name="action" value="bmm_export_csv">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $current_form ); ?>">
		<input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
		<button type="submit" class="page-title-action"><?php esc_html_e( 'Export CSV', 'bmm-registration' ); ?></button>
	</form>
	<form method="post" action="<?php echo $admin_post; ?>" class="bmm-title-action">
		<?php wp_nonce_field( 'bmm_audit_payments', 'bmm_audit_nonce' ); ?>
		<input type="hidden" name="action" value="bmm_audit_payments">
		<button type="submit" class="page-title-action"><?php esc_html_e( 'Audit Payments', 'bmm-registration' ); ?></button>
	</form>
	<form method="post" action="<?php echo $admin_post; ?>" class="bmm-title-action"
		onsubmit="return confirm('<?php echo esc_js( __( 'Mark every unverified completed submission as Failed? Run this after reviewing the flagged rows.', 'bmm-registration' ) ); ?>');">
		<?php wp_nonce_field( 'bmm_revert_unverified', 'bmm_revert_nonce' ); ?>
		<input type="hidden" name="action" value="bmm_revert_unverified">
		<button type="submit" class="page-title-action"><?php esc_html_e( 'Mark Unverified as Failed', 'bmm-registration' ); ?></button>
	</form>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bmm-reconcile' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Reconcile Payments', 'bmm-registration' ); ?></a>

	<hr class="wp-header-end">

	<?php
	// Feedback after a bulk action (see BMM_Admin::process_submissions_bulk_action()).
	if ( isset( $_GET['bmm_bulk'], $_GET['bmm_count'] ) ) :
		$bulk_action = sanitize_key( wp_unslash( $_GET['bmm_bulk'] ) );
		$bulk_count  = (int) $_GET['bmm_count'];
		$bulk_labels = [
			'delete'         => __( 'moved to Trash', 'bmm-registration' ),
			'mark_completed' => __( 'marked as Completed', 'bmm-registration' ),
			'mark_pending'   => __( 'marked as Pending', 'bmm-registration' ),
			'mark_failed'    => __( 'marked as Failed', 'bmm-registration' ),
		];
		if ( isset( $bulk_labels[ $bulk_action ] ) ) :
			$bulk_message = sprintf(
				/* translators: 1: number of submissions, 2: action performed (e.g. "moved to Trash") */
				_n( '%1$d submission %2$s.', '%1$d submissions %2$s.', $bulk_count, 'bmm-registration' ),
				$bulk_count,
				$bulk_labels[ $bulk_action ]
			);
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $bulk_message ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>
	<?php
	// Feedback after the payment audit (see BMM_Admin::handle_payment_audit()).
	if ( isset( $_GET['bmm_audit'] ) ) :
		$flagged = isset( $_GET['bmm_audit_flagged'] ) ? (int) $_GET['bmm_audit_flagged'] : 0;
		if ( $flagged > 0 ) :
			$review_url = add_query_arg(
				[ 'page' => 'bmm-submissions', 'payment_status' => 'unverified' ],
				admin_url( 'admin.php' )
			);
			?>
			<div class="notice notice-warning is-dismissible"><p>
				<?php
				echo esc_html( sprintf(
					/* translators: %d = number of submissions */
					_n(
						'Payment audit: %d completed submission has no recorded payment.',
						'Payment audit: %d completed submissions have no recorded payment.',
						$flagged,
						'bmm-registration'
					),
					$flagged
				) );
				?>
				<a href="<?php echo esc_url( $review_url ); ?>"><?php esc_html_e( 'Review them', 'bmm-registration' ); ?></a>
			</p></div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payment audit: every completed submission has a recorded payment.', 'bmm-registration' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>
	<?php
	// Feedback after the one-click "Mark Unverified as Failed" cleanup.
	if ( isset( $_GET['bmm_reverted'] ) ) :
		$reverted = (int) $_GET['bmm_reverted'];
		?>
		<div class="notice notice-success is-dismissible"><p><?php
			echo esc_html( sprintf(
				/* translators: %d = number of submissions */
				_n(
					'%d unverified submission marked as Failed.',
					'%d unverified submissions marked as Failed.',
					$reverted,
					'bmm-registration'
				),
				$reverted
			) );
		?></p></div>
	<?php endif; ?>

	<?php $list_table->views(); ?>

	<?php
	// Seat subtotals across the whole filtered set (all pages).
	$seat_totals = $list_table->seat_totals;
	?>
	<div class="bmm-seat-totals">
		<span class="bmm-seat-totals__title"><?php
			echo esc_html( sprintf(
				/* translators: %d = number of submissions */
				_n( 'Seat totals (%d submission)', 'Seat totals (%d submissions)', $list_table->total_matching, 'bmm-registration' ),
				$list_table->total_matching
			) );
		?></span>
		<span class="bmm-seat-totals__item"><?php esc_html_e( "Men's (RH)", 'bmm-registration' ); ?>: <strong><?php echo (int) $seat_totals['men_rh']; ?></strong></span>
		<span class="bmm-seat-totals__item"><?php esc_html_e( "Women's (RH)", 'bmm-registration' ); ?>: <strong><?php echo (int) $seat_totals['women_rh']; ?></strong></span>
		<span class="bmm-seat-totals__item"><?php esc_html_e( "Men's (YK)", 'bmm-registration' ); ?>: <strong><?php echo (int) $seat_totals['men_yk']; ?></strong></span>
		<span class="bmm-seat-totals__item"><?php esc_html_e( "Women's (YK)", 'bmm-registration' ); ?>: <strong><?php echo (int) $seat_totals['women_yk']; ?></strong></span>
	</div>

	<form method="get">
		<input type="hidden" name="page" value="bmm-submissions" />
		<div class="bmm-submissions-scroll">
			<?php $list_table->display(); ?>
		</div>
	</form>
</div>
