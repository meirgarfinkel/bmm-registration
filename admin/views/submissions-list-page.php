<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
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
	<form method="get">
		<input type="hidden" name="page" value="bmm-submissions" />
		<?php $list_table->display(); ?>
	</form>
</div>
