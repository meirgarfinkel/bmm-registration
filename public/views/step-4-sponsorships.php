<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Sponsorship Opportunities', 'bmm-registration' ); ?></h3>
<p class="description"><?php esc_html_e( 'Support our community – all sponsorships are optional.', 'bmm-registration' ); ?></p>

<div class="bmm-sponsorships" id="bmm-sponsorships-list">
	<!-- Populated by bmm-form.js from bmmConfig.sponsorships -->
</div>

<div class="bmm-sponsorships bmm-sponsorship-other">
	<div class="bmm-sponsorship-option bmm-sponsorship-other-field" id="bmm-sponsorship-other-field">
		<label for="bmm-sponsorship-other-amount"><strong><?php esc_html_e( 'Other amount', 'bmm-registration' ); ?></strong></label>
		<p class="description"><?php esc_html_e( 'Enter any amount you would like to sponsor.', 'bmm-registration' ); ?></p>
		<input type="number" id="bmm-sponsorship-other-amount" name="sponsorship_other"
			min="0" step="1" inputmode="numeric" placeholder="₪0" />
	</div>
</div>

<div class="bmm-pricing-preview__row" id="bmm-preview-sponsorships" hidden>
	<span><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></span>
	<span id="bmm-preview-sponsorships-amount"></span>
</div>
