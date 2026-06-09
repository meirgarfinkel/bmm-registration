<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Notes & Payment Method', 'bmm-registration' ); ?></h3>

<!-- Order summary — populated by bmm-pricing.js whenever pricing data is available -->
<div class="bmm-order-summary" id="bmm-step5-summary">
	<h4><?php esc_html_e( 'Order Summary', 'bmm-registration' ); ?></h4>
	<div class="bmm-summary-row" id="bmm-step5-membership" hidden>
		<span><?php esc_html_e( 'Membership', 'bmm-registration' ); ?></span>
		<span id="bmm-step5-membership-amount"></span>
	</div>
	<div class="bmm-summary-row" id="bmm-step5-extra-men" hidden>
		<span id="bmm-step5-extra-men-label"></span>
		<span id="bmm-step5-extra-men-amount"></span>
	</div>
	<div class="bmm-summary-row" id="bmm-step5-extra-women" hidden>
		<span id="bmm-step5-extra-women-label"></span>
		<span id="bmm-step5-extra-women-amount"></span>
	</div>
	<div class="bmm-summary-row" id="bmm-step5-guest-men" hidden>
		<span id="bmm-step5-guest-men-label"></span>
		<span id="bmm-step5-guest-men-amount"></span>
	</div>
	<div class="bmm-summary-row" id="bmm-step5-guest-women" hidden>
		<span id="bmm-step5-guest-women-label"></span>
		<span id="bmm-step5-guest-women-amount"></span>
	</div>
	<div class="bmm-summary-row" id="bmm-step5-sponsorships" hidden>
		<span id="bmm-step5-sponsorships-label"><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></span>
		<span id="bmm-step5-sponsorships-amount"></span>
	</div>
	<div class="bmm-summary-row bmm-summary-row--total">
		<strong><?php esc_html_e( 'Total', 'bmm-registration' ); ?></strong>
		<strong id="bmm-step5-total">₪0</strong>
	</div>
</div>

<div class="bmm-field">
	<label for="bmm_notes"><?php esc_html_e( 'Notes', 'bmm-registration' ); ?> <span class="bmm-optional"><?php esc_html_e( '(optional)', 'bmm-registration' ); ?></span></label>
	<textarea id="bmm_notes" name="notes" rows="4" placeholder="<?php esc_attr_e( 'Any additional information or special requests...', 'bmm-registration' ); ?>"></textarea>
</div>

<!-- Payment method: pay in full (Ragil) or in installments (Tashlumim).
     The Tashlumim option + the count selector are shown by bmm-form.js only
     when the form allows installments (maxInstallments > 1). -->
<div class="bmm-field" id="bmm-payment-type-field">
	<label><?php esc_html_e( 'Payment Method', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
	<div class="bmm-radio-group" role="radiogroup">
		<label class="bmm-radio" id="bmm-option-ragil">
			<input type="radio" name="payment_type" value="Ragil" checked />
			<span>
				<strong><?php esc_html_e( 'Pay in full', 'bmm-registration' ); ?></strong>
				<span class="description"><?php esc_html_e( 'One credit-card payment for the full amount', 'bmm-registration' ); ?></span>
			</span>
		</label>
		<label class="bmm-radio" id="bmm-option-tashlumim" hidden>
			<input type="radio" name="payment_type" value="Tashlumim" />
			<span>
				<strong><?php esc_html_e( 'Pay in installments (Tashlumim)', 'bmm-registration' ); ?></strong>
				<span class="description"><?php esc_html_e( 'Split the total into equal monthly credit-card payments', 'bmm-registration' ); ?></span>
			</span>
		</label>
	</div>
</div>

<!-- Number-of-payments selector — shown when "Tashlumim" is chosen. -->
<div class="bmm-field" id="bmm-tashlumim-field" hidden>
	<label for="bmm_tashlumim"><?php esc_html_e( 'Number of payments', 'bmm-registration' ); ?></label>
	<select id="bmm_tashlumim" name="tashlumim"></select>
	<p class="description" id="bmm-tashlumim-hint"></p>
</div>
