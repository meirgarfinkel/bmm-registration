<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Notes & Payment Method', 'bmm-registration' ); ?></h3>

<div class="bmm-field">
	<label for="bmm_notes"><?php esc_html_e( 'Notes', 'bmm-registration' ); ?> <span class="bmm-optional"><?php esc_html_e( '(optional)', 'bmm-registration' ); ?></span></label>
	<textarea id="bmm_notes" name="notes" rows="4" placeholder="<?php esc_attr_e( 'Any additional information or special requests...', 'bmm-registration' ); ?>"></textarea>
</div>

<!-- Payment type — shown/hidden based on form config (controlled by JS) -->
<div class="bmm-field" id="bmm-payment-type-field">
	<label><?php esc_html_e( 'Payment Method', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
	<div class="bmm-radio-group" role="radiogroup">
		<label class="bmm-radio" id="bmm-option-ragil">
			<input type="radio" name="payment_type" value="Ragil" checked />
			<span>
				<strong><?php esc_html_e( 'Regular Payment', 'bmm-registration' ); ?></strong>
				<span class="description"><?php esc_html_e( 'One-time credit card payment', 'bmm-registration' ); ?></span>
			</span>
		</label>
		<label class="bmm-radio" id="bmm-option-hk">
			<input type="radio" name="payment_type" value="HK" />
			<span>
				<strong><?php esc_html_e( 'Horaat Keva', 'bmm-registration' ); ?></strong>
				<span class="description"><?php esc_html_e( 'Monthly standing order – spread payments over multiple months', 'bmm-registration' ); ?></span>
			</span>
		</label>
	</div>
</div>
