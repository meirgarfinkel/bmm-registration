<?php
defined( 'ABSPATH' ) || exit;
// Variables: $post (WP_Post), $meta (array), $form_config (BMM_Form_Config|null), $submission_id (int)

$editing = ! empty( $_GET['edit'] );

$davening_labels = BMM_Pricing::DAVENINGS;

$children    = is_array( $meta['children_hebrew_names'] ) ? $meta['children_hebrew_names'] : [];
$seats_men   = is_array( $meta['seats_men'] )   ? $meta['seats_men']   : [];
$seats_women = is_array( $meta['seats_women'] ) ? $meta['seats_women'] : [];

$view_url = admin_url( 'admin.php?page=bmm-submissions&submission_id=' . $submission_id );
$edit_url = add_query_arg( 'edit', 1, $view_url );

$selected_sponsorship_labels = [];
if ( $form_config && is_array( $meta['sponsorships_selected'] ) ) {
	$map = array_column( $form_config->enabled_sponsorships(), 'label', 'id' );
	foreach ( $meta['sponsorships_selected'] as $id ) {
		if ( isset( $map[ $id ] ) ) {
			$selected_sponsorship_labels[] = $map[ $id ];
		}
	}
}
if ( ! empty( $meta['sponsorship_other'] ) ) {
	$selected_sponsorship_labels[] = sprintf(
		/* translators: %d: custom sponsorship amount in NIS */
		__( 'Other: ₪%d', 'bmm-registration' ),
		(int) $meta['sponsorship_other']
	);
}
?>
<div class="wrap bmm-submission-detail">
	<h1>
		<?php echo esc_html( $post->post_title ); ?>
		<span class="bmm-status bmm-status--<?php echo esc_attr( $post->post_status ); ?>"><?php echo esc_html( ucfirst( $post->post_status ) ); ?></span>
		<?php if ( ! $editing ) : ?>
		<a href="<?php echo esc_url( $edit_url ); ?>" class="page-title-action"><?php esc_html_e( 'Edit', 'bmm-registration' ); ?></a>
		<?php endif; ?>
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bmm-submissions' ) ); ?>">&larr; <?php esc_html_e( 'Back to Submissions', 'bmm-registration' ); ?></a>

	<?php if ( isset( $_GET['bmm_saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission saved.', 'bmm-registration' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $meta['amount_mismatch'] ) ) : ?>
	<div class="notice notice-warning"><p><?php esc_html_e( '⚠️ Amount mismatch: the callback amount differs from the stored total. Review manually.', 'bmm-registration' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $editing ) : ?>

	<!-- EDIT MODE. This <form> is a sibling of the status form below (never
	     nested), so both submit correctly. -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bmm-edit-submission">
		<?php wp_nonce_field( 'bmm_edit_submission', 'bmm_edit_nonce' ); ?>
		<input type="hidden" name="action" value="bmm_update_submission">
		<input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission_id ); ?>">

		<div class="bmm-detail-columns">

			<div class="bmm-detail-section">
				<h2><?php esc_html_e( 'Personal Information', 'bmm-registration' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="bmm-first_name"><?php esc_html_e( 'First Name', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-first_name" name="first_name" class="regular-text" value="<?php echo esc_attr( $meta['first_name'] ); ?>"></td></tr>
					<tr><th><label for="bmm-last_name"><?php esc_html_e( 'Last Name', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-last_name" name="last_name" class="regular-text" value="<?php echo esc_attr( $meta['last_name'] ); ?>"></td></tr>
					<tr><th><label for="bmm-email"><?php esc_html_e( 'Email', 'bmm-registration' ); ?></label></th>
						<td><input type="email" id="bmm-email" name="email" class="regular-text" value="<?php echo esc_attr( $meta['email'] ); ?>"></td></tr>
					<tr><th><label for="bmm-phone"><?php esc_html_e( 'Phone', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-phone" name="phone" class="regular-text" value="<?php echo esc_attr( $meta['phone'] ); ?>"></td></tr>
					<tr><th><label for="bmm-city"><?php esc_html_e( 'City', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-city" name="city" class="regular-text" value="<?php echo esc_attr( $meta['city'] ); ?>"></td></tr>
					<tr><th><label for="bmm-address"><?php esc_html_e( 'Address', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-address" name="address" class="regular-text" value="<?php echo esc_attr( $meta['address'] ); ?>"></td></tr>
					<tr><th><label for="bmm-zeout"><?php esc_html_e( 'Israeli ID (ת.ז.)', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-zeout" name="zeout" class="regular-text" value="<?php echo esc_attr( $meta['zeout'] ); ?>"></td></tr>
				</table>
			</div>

			<div class="bmm-detail-section">
				<h2><?php esc_html_e( 'Hebrew Names', 'bmm-registration' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="bmm-hebrew_name"><?php esc_html_e( 'Hebrew Name', 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-hebrew_name" name="hebrew_name" class="regular-text" dir="rtl" value="<?php echo esc_attr( $meta['hebrew_name'] ); ?>"></td></tr>
					<tr><th><label for="bmm-tribe"><?php esc_html_e( 'Tribe', 'bmm-registration' ); ?></label></th>
						<td><select id="bmm-tribe" name="tribe">
							<?php foreach ( [ 'kohen' => 'Kohen', 'levi' => 'Levi', 'yisrael' => 'Yisrael' ] as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $meta['tribe'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<tr><th><label for="bmm-wife_hebrew_name"><?php esc_html_e( "Wife's Hebrew Name", 'bmm-registration' ); ?></label></th>
						<td><input type="text" id="bmm-wife_hebrew_name" name="wife_hebrew_name" class="regular-text" dir="rtl" value="<?php echo esc_attr( $meta['wife_hebrew_name'] ); ?>"></td></tr>
					<tr><th><label for="bmm-children"><?php esc_html_e( "Children's Hebrew Names", 'bmm-registration' ); ?></label></th>
						<td><textarea id="bmm-children" name="children_hebrew_names" rows="4" class="regular-text" dir="rtl" placeholder="<?php esc_attr_e( 'One name per line', 'bmm-registration' ); ?>"><?php echo esc_textarea( implode( "\n", $children ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One name per line.', 'bmm-registration' ); ?></p></td></tr>
				</table>
			</div>

			<div class="bmm-detail-section">
				<h2><?php esc_html_e( 'Membership & Seats', 'bmm-registration' ); ?></h2>
				<p>
					<label><input type="checkbox" name="wants_membership" value="1" <?php checked( ! empty( $meta['wants_membership'] ) ); ?>> <?php esc_html_e( 'Membership', 'bmm-registration' ); ?></label><br>
					<label><input type="checkbox" name="has_horaat_keva" value="1" <?php checked( ! empty( $meta['has_horaat_keva'] ) ); ?>> <?php esc_html_e( 'Horaat Keva (membership paid separately)', 'bmm-registration' ); ?></label><br>
					<label><input type="checkbox" name="wants_guest_seats" value="1" <?php checked( ! empty( $meta['wants_guest_seats'] ) ); ?>> <?php esc_html_e( 'Guest seats', 'bmm-registration' ); ?></label>
				</p>
				<table class="widefat striped" style="margin-top:8px;">
					<thead><tr><th><?php esc_html_e( 'Davening', 'bmm-registration' ); ?></th><th><?php esc_html_e( 'Men', 'bmm-registration' ); ?></th><th><?php esc_html_e( 'Women', 'bmm-registration' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $davening_labels as $key => $label ) : ?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><input type="number" min="0" style="width:70px;" name="seats_men[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (int) ( $seats_men[ $key ] ?? 0 ) ); ?>"></td>
						<td><input type="number" min="0" style="width:70px;" name="seats_women[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (int) ( $seats_women[ $key ] ?? 0 ) ); ?>"></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="bmm-detail-section">
				<h2><?php esc_html_e( 'Sponsorships & Notes', 'bmm-registration' ); ?></h2>
				<table class="widefat striped">
					<tr>
						<th><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></th>
						<td><?php echo $selected_sponsorship_labels ? esc_html( implode( ', ', $selected_sponsorship_labels ) ) : esc_html__( 'None', 'bmm-registration' ); ?>
							<span class="description">(<?php esc_html_e( 'not editable here', 'bmm-registration' ); ?>)</span></td>
					</tr>
					<?php if ( ! empty( $meta['kiddush_date'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Kiddush Date', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['kiddush_date'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $meta['kiddush_dedication'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Kiddush Dedication', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['kiddush_dedication'] ); ?></td></tr>
					<?php endif; ?>
				</table>
				<p style="margin-top:8px;">
					<label for="bmm-notes"><strong><?php esc_html_e( 'Notes', 'bmm-registration' ); ?></strong></label><br>
					<textarea id="bmm-notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $meta['notes'] ); ?></textarea>
				</p>
			</div>

		</div>

		<p class="submit">
			<?php submit_button( __( 'Save changes', 'bmm-registration' ), 'primary', 'bmm_save', false ); ?>
			<a href="<?php echo esc_url( $view_url ); ?>" class="button" style="margin-left:6px;"><?php esc_html_e( 'Cancel', 'bmm-registration' ); ?></a>
			<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Seats & membership are re-priced on save; the recorded payment is not changed.', 'bmm-registration' ); ?></span>
		</p>
	</form>

	<?php else : ?>

	<!-- VIEW MODE (read-only). -->
	<div class="bmm-detail-columns">

		<div class="bmm-detail-section">
			<h2><?php esc_html_e( 'Personal Information', 'bmm-registration' ); ?></h2>
			<table class="widefat striped">
				<tr><th><?php esc_html_e( 'Name', 'bmm-registration' ); ?></th><td><?php echo esc_html( trim( $meta['first_name'] . ' ' . $meta['last_name'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['email'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Phone', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['phone'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'City / Address', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['city'] . ( $meta['address'] ? ', ' . $meta['address'] : '' ) ); ?></td></tr>
				<?php if ( $meta['zeout'] ) : ?>
				<tr><th><?php esc_html_e( 'Israeli ID (ת.ז.)', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['zeout'] ); ?></td></tr>
				<?php endif; ?>
			</table>
		</div>

		<div class="bmm-detail-section">
			<h2><?php esc_html_e( 'Hebrew Names', 'bmm-registration' ); ?></h2>
			<table class="widefat striped">
				<tr><th><?php esc_html_e( 'Hebrew Name', 'bmm-registration' ); ?></th><td dir="rtl"><?php echo esc_html( $meta['hebrew_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Tribe', 'bmm-registration' ); ?></th><td><?php echo esc_html( ucfirst( $meta['tribe'] ) ); ?></td></tr>
				<?php if ( $meta['wife_hebrew_name'] ) : ?>
				<tr><th><?php esc_html_e( "Wife's Hebrew Name", 'bmm-registration' ); ?></th><td dir="rtl"><?php echo esc_html( $meta['wife_hebrew_name'] ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $children ) : ?>
				<tr><th><?php esc_html_e( "Children's Hebrew Names", 'bmm-registration' ); ?></th><td dir="rtl"><?php echo esc_html( implode( ', ', $children ) ); ?></td></tr>
				<?php endif; ?>
			</table>
		</div>

		<div class="bmm-detail-section">
			<h2><?php esc_html_e( 'Membership & Seats', 'bmm-registration' ); ?></h2>
			<table class="widefat striped">
				<tr><th><?php esc_html_e( 'Membership', 'bmm-registration' ); ?></th><td><?php echo $meta['wants_membership'] ? esc_html__( 'Yes', 'bmm-registration' ) : esc_html__( 'No', 'bmm-registration' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Horaat Keva (separate)', 'bmm-registration' ); ?></th><td><?php echo ! empty( $meta['has_horaat_keva'] ) ? esc_html__( 'Yes', 'bmm-registration' ) : esc_html__( 'No', 'bmm-registration' ); ?></td></tr>
			</table>
			<table class="widefat striped" style="margin-top:8px;">
				<thead><tr><th><?php esc_html_e( 'Davening', 'bmm-registration' ); ?></th><th><?php esc_html_e( 'Men', 'bmm-registration' ); ?></th><th><?php esc_html_e( 'Women', 'bmm-registration' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $davening_labels as $key => $label ) : ?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( (int) ( $seats_men[ $key ] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( (int) ( $seats_women[ $key ] ?? 0 ) ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="bmm-detail-section">
			<h2><?php esc_html_e( 'Sponsorships & Notes', 'bmm-registration' ); ?></h2>
			<table class="widefat striped">
				<tr>
					<th><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></th>
					<td><?php echo $selected_sponsorship_labels ? esc_html( implode( ', ', $selected_sponsorship_labels ) ) : esc_html__( 'None', 'bmm-registration' ); ?></td>
				</tr>
				<?php if ( ! empty( $meta['kiddush_date'] ) ) : ?>
				<tr><th><?php esc_html_e( 'Kiddush Date', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['kiddush_date'] ); ?></td></tr>
				<?php endif; ?>
				<?php if ( ! empty( $meta['kiddush_dedication'] ) ) : ?>
				<tr><th><?php esc_html_e( 'Kiddush Dedication', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['kiddush_dedication'] ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $meta['notes'] ) : ?>
				<tr><th><?php esc_html_e( 'Notes', 'bmm-registration' ); ?></th><td><?php echo nl2br( esc_html( $meta['notes'] ) ); ?></td></tr>
				<?php endif; ?>
			</table>
		</div>

	</div>

	<?php endif; ?>

	<!-- PAYMENT (read-only in both modes; its status form is a top-level sibling). -->
	<div class="bmm-detail-columns">
		<div class="bmm-detail-section">
			<h2><?php esc_html_e( 'Payment', 'bmm-registration' ); ?></h2>
			<table class="widefat striped">
				<tr><th><?php esc_html_e( 'Payment Type', 'bmm-registration' ); ?></th><td><?php
					if ( $meta['payment_type'] === 'Tashlumim' ) {
						$n = (int) ( $meta['tashlumim'] ?? 0 );
						echo $n > 1
							? esc_html( sprintf( __( 'Tashlumim (%d payments)', 'bmm-registration' ), $n ) )
							: esc_html__( 'Tashlumim', 'bmm-registration' );
					} else {
						esc_html_e( 'Pay in full', 'bmm-registration' );
					}
				?></td></tr>
				<tr><th><?php esc_html_e( 'Membership Fee', 'bmm-registration' ); ?></th><td>₪<?php echo esc_html( $meta['price_membership'] ); ?></td></tr>
				<tr><th><?php esc_html_e( "Extra Men's Seats", 'bmm-registration' ); ?></th><td>₪<?php echo esc_html( $meta['price_extra_men'] ); ?></td></tr>
				<tr><th><?php esc_html_e( "Extra Women's Seats", 'bmm-registration' ); ?></th><td>₪<?php echo esc_html( $meta['price_extra_women'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></th><td>₪<?php echo esc_html( $meta['price_sponsorships'] ); ?></td></tr>
				<tr><th><strong><?php esc_html_e( 'Total', 'bmm-registration' ); ?></strong></th><td><strong>₪<?php echo esc_html( $meta['price_total'] ); ?></strong></td></tr>
				<tr><th><?php esc_html_e( 'Transaction ID', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['nedarim_transaction_id'] ?: $meta['nedarim_keva_id'] ?: '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Approval Number', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['nedarim_confirmation'] ?: '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Card Last 4', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['nedarim_last_num'] ?: '—' ); ?></td></tr>
				<?php if ( ! empty( $meta['zero_total'] ) ) : ?>
				<tr><th><?php esc_html_e( 'Payment', 'bmm-registration' ); ?></th><td><?php esc_html_e( 'None — ₪0 order (membership paid externally, no extra seats)', 'bmm-registration' ); ?></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Completed At', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['payment_completed_at'] ?: '—' ); ?></td></tr>
				<?php if ( ! empty( $meta['failed_attempts'] ) ) : ?>
				<tr><th><?php esc_html_e( 'Failed Payment Attempts', 'bmm-registration' ); ?></th><td><?php echo esc_html( (string) (int) $meta['failed_attempts'] ); ?></td></tr>
				<?php endif; ?>
				<?php if ( ! empty( $meta['amount_mismatch'] ) ) : ?>
				<tr><th><?php esc_html_e( 'Amount Mismatch', 'bmm-registration' ); ?></th><td><strong style="color:#b32d2e;"><?php esc_html_e( 'Yes — callback amount differed from the locked total', 'bmm-registration' ); ?></strong></td></tr>
				<?php endif; ?>
			</table>

			<h3><?php esc_html_e( 'Update Payment Status', 'bmm-registration' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bmm_update_status', 'bmm_status_nonce' ); ?>
				<input type="hidden" name="action" value="bmm_update_submission_status">
				<input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission_id ); ?>">
				<select name="new_status">
					<?php foreach ( [ 'bmm_pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed' ] as $s => $s_label ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $post->post_status, $s ); ?>><?php echo esc_html( $s_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Update Status', 'bmm-registration' ), 'secondary', '', false ); ?>
			</form>
		</div>
	</div>
</div>
