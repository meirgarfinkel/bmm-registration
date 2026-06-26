<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Sponsorship Opportunities', 'bmm-registration' ); ?></h3>
<p class="description"><?php esc_html_e( 'Support our community – all sponsorships are optional.', 'bmm-registration' ); ?></p>

<div class="bmm-sponsorships" id="bmm-sponsorships-list">
	<!-- Populated by bmm-form.js from bmmConfig.sponsorships -->
</div>

<div class="bmm-sponsorships bmm-sponsorship-other">
	<label class="bmm-checkbox bmm-sponsorship-option">
		<input type="checkbox" id="bmm-sponsorship-other-toggle" />
		<span><strong><?php esc_html_e( 'Other', 'bmm-registration' ); ?></strong></span>
	</label>
	<div class="bmm-sponsorship-other-field" id="bmm-sponsorship-other-field" hidden>
		<label for="bmm-sponsorship-other-amount"><?php esc_html_e( 'Amount (₪)', 'bmm-registration' ); ?></label>
		<input type="number" id="bmm-sponsorship-other-amount" name="sponsorship_other"
			min="0" step="1" inputmode="numeric" placeholder="0" />
	</div>
</div>

<div class="bmm-pricing-preview__row" id="bmm-preview-sponsorships" hidden>
	<span><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></span>
	<span id="bmm-preview-sponsorships-amount"></span>
</div>
