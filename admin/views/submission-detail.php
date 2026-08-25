<?php
defined( 'ABSPATH' ) || exit;
// Variables: $post (WP_Post), $meta (array), $form_config (BMM_Form_Config|null), $submission_id (int)

$davening_labels = BMM_Pricing::DAVENINGS;
$dav_keys        = array_keys( $davening_labels );

$children = is_array( $meta['children_hebrew_names'] ) ? $meta['children_hebrew_names'] : [];
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
	</h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bmm-submissions' ) ); ?>">&larr; <?php esc_html_e( 'Back to Submissions', 'bmm-registration' ); ?></a>

	<?php if ( ! empty( $meta['amount_mismatch'] ) ) : ?>
	<div class="notice notice-warning"><p><?php esc_html_e( '⚠️ Amount mismatch: the callback amount differs from the stored total. Review manually.', 'bmm-registration' ); ?></p></div>
	<?php endif; ?>

	<div class="bmm-detail-columns">

		<div class="bmm-detail-section">
			<h2><?php esc_html_e( 'Personal Information', 'bmm-registration' ); ?></h2>
			<table class="widefat striped">
				<tr><th><?php esc_html_e( 'Name', 'bmm-registration' ); ?></th><td><?php echo esc_html( $meta['first_name'] . ' ' . $meta['last_name'] ); ?></td></tr>
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
				<tr>
					<th><?php esc_html_e( "Children's Hebrew Names", 'bmm-registration' ); ?></th>
					<td dir="rtl"><?php echo esc_html( implode( ', ', $children ) ); ?></td>
				</tr>
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
				<?php
				$seats_men   = is_array( $meta['seats_men'] )   ? $meta['seats_men']   : [];
				$seats_women = is_array( $meta['seats_women'] ) ? $meta['seats_women'] : [];
				foreach ( $davening_labels as $key => $label ) :
				?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( $seats_men[ $key ] ?? 0 ); ?></td>
					<td><?php echo esc_html( $seats_women[ $key ] ?? 0 ); ?></td>
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
