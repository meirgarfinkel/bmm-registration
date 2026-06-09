<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Registration Forms', 'bmm-registration' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bmm_reg_form' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Form', 'bmm-registration' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( empty( $forms ) ) : ?>
		<p><?php esc_html_e( 'No forms found. Create your first registration form.', 'bmm-registration' ); ?></p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Submissions', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Public Link', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Date', 'bmm-registration' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $forms as $form ) :
			$edit_url = get_edit_post_link( $form->ID );
			$sub_count_q = new WP_Query( [ 'post_type' => 'bmm_submission', 'post_parent' => $form->ID, 'post_status' => [ 'bmm_pending', 'completed', 'failed' ], 'posts_per_page' => -1, 'fields' => 'ids' ] );
			$sub_count = $sub_count_q->found_posts;
			$public_url = $form->post_name
				? home_url( '/membership/' . $form->post_name . '/' )
				: add_query_arg( 'bmm_form', $form->ID, home_url( '/' ) );
			$status_label = ( $form->post_status === 'publish' ) ? 'Published' : ucfirst( $form->post_status );
		?>
			<tr>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $form->post_title ); ?></strong></a>
					<div class="row-actions">
						<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'bmm-registration' ); ?></a> | </span>
						<span class="view"><a href="<?php echo esc_url( admin_url( 'admin.php?page=bmm-submissions&form_id=' . $form->ID ) ); ?>"><?php esc_html_e( 'View Submissions', 'bmm-registration' ); ?></a></span>
					</div>
				</td>
				<td><span class="bmm-status bmm-status--<?php echo esc_attr( $form->post_status ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
				<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bmm-submissions&form_id=' . $form->ID ) ); ?>"><?php echo esc_html( $sub_count ); ?></a></td>
				<td>
					<?php if ( in_array( $form->post_status, [ 'published', 'publish' ], true ) ) : ?>
						<a href="<?php echo esc_url( $public_url ); ?>" target="_blank"><?php echo esc_html( $public_url ); ?></a>
					<?php elseif ( $form->post_status === 'archived' ) : ?>
						<span class="description"><?php esc_html_e( 'Archived (closed)', 'bmm-registration' ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $public_url ); ?>" target="_blank"><?php esc_html_e( 'Preview (draft)', 'bmm-registration' ); ?></a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( get_the_date( 'Y-m-d', $form->ID ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
