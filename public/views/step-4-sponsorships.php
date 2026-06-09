<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Sponsorship Opportunities', 'bmm-registration' ); ?></h3>
<p class="description"><?php esc_html_e( 'Support our community – all sponsorships are optional.', 'bmm-registration' ); ?></p>

<div class="bmm-sponsorships" id="bmm-sponsorships-list">
	<!-- Populated by bmm-form.js from bmmConfig.sponsorships -->
</div>

<div class="bmm-pricing-preview__row" id="bmm-preview-sponsorships" hidden>
	<span><?php esc_html_e( 'Sponsorships', 'bmm-registration' ); ?></span>
	<span id="bmm-preview-sponsorships-amount"></span>
</div>
