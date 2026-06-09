<?php
defined( 'ABSPATH' ) || exit;
// $post and $config (BMM_Form_Config|null) are available from the meta box callback.

$m = function( string $key, mixed $default = '' ) use ( $post, $config ): mixed {
	if ( $config ) {
		return match ( $key ) {
			'membership_price'          => $config->membership_price,
			'membership_included_men'   => $config->membership_included_men,
			'membership_included_women' => $config->membership_included_women,
			'extra_seat_price'          => $config->extra_seat_price,
				'guest_seat_price'          => $config->guest_seat_price,
			'payment_options'           => $config->payment_options,
			'ragil_max_payments'        => $config->ragil_max_payments,
			'hk_max_months'             => $config->hk_max_months,
			'mosad'                     => get_post_meta( $post->ID, '_bmm_form_mosad', true ),
			'api_valid'                 => get_post_meta( $post->ID, '_bmm_form_api_valid', true ),
			'sponsorships'              => $config->sponsorships,
			default                     => $default,
		};
	}
	return get_post_meta( $post->ID, "_bmm_form_{$key}", true ) ?: $default;
};

$sponsorships = $config ? $config->sponsorships : BMM_Form_Config::default_sponsorships();
?>

<div class="bmm-form-editor">

	<h3><?php esc_html_e( 'Membership', 'bmm-registration' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="bmm_form_membership_price"><?php esc_html_e( 'Membership Price (₪)', 'bmm-registration' ); ?></label></th>
			<td><input type="number" id="bmm_form_membership_price" name="bmm_form_membership_price" value="<?php echo esc_attr( $m( 'membership_price', 0 ) ); ?>" min="0" class="small-text" /></td>
		</tr>
		<tr>
			<th><label for="bmm_form_membership_included_men"><?php esc_html_e( "Men's Seats Included in Membership", 'bmm-registration' ); ?></label></th>
			<td><input type="number" id="bmm_form_membership_included_men" name="bmm_form_membership_included_men" value="<?php echo esc_attr( $m( 'membership_included_men', 0 ) ); ?>" min="0" class="small-text" /></td>
		</tr>
		<tr>
			<th><label for="bmm_form_membership_included_women"><?php esc_html_e( "Women's Seats Included in Membership", 'bmm-registration' ); ?></label></th>
			<td><input type="number" id="bmm_form_membership_included_women" name="bmm_form_membership_included_women" value="<?php echo esc_attr( $m( 'membership_included_women', 0 ) ); ?>" min="0" class="small-text" /></td>
		</tr>
		<tr>
			<th><label for="bmm_form_extra_seat_price"><?php esc_html_e( 'Additional Seat Price (₪)', 'bmm-registration' ); ?></label></th>
			<td>
				<input type="number" id="bmm_form_extra_seat_price" name="bmm_form_extra_seat_price" value="<?php echo esc_attr( $m( 'extra_seat_price', 0 ) ); ?>" min="0" class="small-text" />
				<p class="description"><?php esc_html_e( 'Charged per extra seat beyond those included in membership (based on peak davening).', 'bmm-registration' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="bmm_form_guest_seat_price"><?php esc_html_e( 'Guest Seat Price (₪)', 'bmm-registration' ); ?></label></th>
			<td>
				<input type="number" id="bmm_form_guest_seat_price" name="bmm_form_guest_seat_price" value="<?php echo esc_attr( $m( 'guest_seat_price', 0 ) ); ?>" min="0" class="small-text" />
				<p class="description"><?php esc_html_e( 'Per-seat price for "Guest Seats – No Membership". Every seat is charged at this rate (no included seats).', 'bmm-registration' ); ?></p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Payment Options', 'bmm-registration' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Allowed Payment Methods', 'bmm-registration' ); ?></th>
			<td>
				<?php $po = $m( 'payment_options', 'both' ); ?>
				<label><input type="radio" name="bmm_form_payment_options" value="both"  <?php checked( $po, 'both' ); ?> /> <?php esc_html_e( 'Both (Regular & Horaat Keva)', 'bmm-registration' ); ?></label><br>
				<label><input type="radio" name="bmm_form_payment_options" value="ragil" <?php checked( $po, 'ragil' ); ?> /> <?php esc_html_e( 'Regular (one-time) only', 'bmm-registration' ); ?></label><br>
				<label><input type="radio" name="bmm_form_payment_options" value="hk"    <?php checked( $po, 'hk' ); ?> /> <?php esc_html_e( 'Horaat Keva (standing order) only', 'bmm-registration' ); ?></label>
			</td>
		</tr>
		<tr>
			<th><label for="bmm_form_ragil_max_payments"><?php esc_html_e( 'Regular – Max Installments', 'bmm-registration' ); ?></label></th>
			<td>
				<input type="number" id="bmm_form_ragil_max_payments" name="bmm_form_ragil_max_payments" value="<?php echo esc_attr( $m( 'ragil_max_payments', 1 ) ); ?>" min="1" class="small-text" />
				<p class="description"><?php esc_html_e( 'Largest number of monthly payments a customer may split a regular charge into. 1 = single payment only (no chooser shown).', 'bmm-registration' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="bmm_form_hk_max_months"><?php esc_html_e( 'Horaat Keva – Max Months', 'bmm-registration' ); ?></label></th>
			<td>
				<input type="number" id="bmm_form_hk_max_months" name="bmm_form_hk_max_months" value="<?php echo esc_attr( $m( 'hk_max_months', 0 ) ); ?>" min="0" class="small-text" />
				<p class="description"><?php esc_html_e( 'Largest number of months a customer may choose for a standing order. 0 = unlimited (charge until cancelled; no chooser shown).', 'bmm-registration' ); ?></p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Sponsorship Opportunities', 'bmm-registration' ); ?></h3>
	<div id="bmm-sponsorships-repeater">
		<table class="widefat" id="bmm-sponsorships-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'bmm-registration' ); ?></th>
					<th><?php esc_html_e( 'ID (no spaces)', 'bmm-registration' ); ?></th>
					<th><?php esc_html_e( 'Label', 'bmm-registration' ); ?></th>
					<th><?php esc_html_e( 'Amount (₪)', 'bmm-registration' ); ?></th>
					<th><?php esc_html_e( 'Remove', 'bmm-registration' ); ?></th>
				</tr>
			</thead>
			<tbody id="bmm-sponsorships-body">
				<?php foreach ( $sponsorships as $i => $s ) : ?>
				<tr class="bmm-sponsorship-row">
					<td><input type="checkbox" name="bmm_sponsorship_enabled[]" value="<?php echo esc_attr( $i ); ?>" <?php checked( ! empty( $s['enabled'] ) ); ?> /></td>
					<td><input type="text" name="bmm_sponsorship_id[]" value="<?php echo esc_attr( $s['id'] ?? '' ); ?>" class="regular-text" /></td>
					<td><input type="text" name="bmm_sponsorship_label[]" value="<?php echo esc_attr( $s['label'] ?? '' ); ?>" class="regular-text" /></td>
					<td><input type="number" name="bmm_sponsorship_amount[]" value="<?php echo esc_attr( $s['amount'] ?? 0 ); ?>" min="0" class="small-text" /></td>
					<td><button type="button" class="button bmm-remove-sponsorship"><?php esc_html_e( 'Remove', 'bmm-registration' ); ?></button></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<button type="button" class="button" id="bmm-add-sponsorship" style="margin-top:8px;"><?php esc_html_e( '+ Add Sponsorship', 'bmm-registration' ); ?></button>
	</div>

	<h3><?php esc_html_e( 'Nedarim Plus Credentials', 'bmm-registration' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Leave blank to use the global settings.', 'bmm-registration' ); ?></p>
	<table class="form-table">
		<tr>
			<th><label for="bmm_form_mosad"><?php esc_html_e( 'Institution ID (Mosad)', 'bmm-registration' ); ?></label></th>
			<td><input type="text" id="bmm_form_mosad" name="bmm_form_mosad" value="<?php echo esc_attr( $m( 'mosad' ) ); ?>" class="regular-text" maxlength="7" /></td>
		</tr>
		<tr>
			<th><label for="bmm_form_api_valid"><?php esc_html_e( 'API Verification Code (ApiValid)', 'bmm-registration' ); ?></label></th>
			<td><input type="text" id="bmm_form_api_valid" name="bmm_form_api_valid" value="<?php echo esc_attr( $m( 'api_valid' ) ); ?>" class="regular-text" maxlength="10" /></td>
		</tr>
	</table>

</div>
