<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bmm-submissions-wrap">
	<h1><?php esc_html_e( 'Submissions', 'bmm-registration' ); ?></h1>
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
	<form method="get">
		<input type="hidden" name="page" value="bmm-submissions" />
		<div class="bmm-submissions-scroll">
			<?php $list_table->display(); ?>
		</div>
	</form>
</div>
