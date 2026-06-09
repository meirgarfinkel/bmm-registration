<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Personal Information', 'bmm-registration' ); ?></h3>

<div class="bmm-field-row">
	<div class="bmm-field">
		<label for="bmm_first_name"><?php esc_html_e( 'First Name', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
		<input type="text" id="bmm_first_name" name="first_name" autocomplete="given-name" required />
	</div>
	<div class="bmm-field">
		<label for="bmm_last_name"><?php esc_html_e( 'Last Name', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
		<input type="text" id="bmm_last_name" name="last_name" autocomplete="family-name" required />
	</div>
</div>

<div class="bmm-field-row">
	<div class="bmm-field">
		<label for="bmm_phone"><?php esc_html_e( 'Phone Number', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
		<input type="tel" id="bmm_phone" name="phone" autocomplete="tel" required />
	</div>
	<div class="bmm-field">
		<label for="bmm_email"><?php esc_html_e( 'Email Address', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
		<input type="email" id="bmm_email" name="email" autocomplete="email" required />
	</div>
</div>

<div class="bmm-field-row">
	<div class="bmm-field">
		<label for="bmm_city"><?php esc_html_e( 'City', 'bmm-registration' ); ?></label>
		<input type="text" id="bmm_city" name="city" autocomplete="address-level2" />
	</div>
	<div class="bmm-field">
		<label for="bmm_address"><?php esc_html_e( 'Street Address', 'bmm-registration' ); ?></label>
		<input type="text" id="bmm_address" name="address" autocomplete="street-address" />
	</div>
</div>

<div class="bmm-field">
	<label for="bmm_zeout"><?php esc_html_e( 'Israeli ID Number (ת.ז.)', 'bmm-registration' ); ?> <span class="bmm-optional"><?php esc_html_e( '(optional)', 'bmm-registration' ); ?></span></label>
	<input type="text" id="bmm_zeout" name="zeout" inputmode="numeric" pattern="[0-9]{1,9}" maxlength="9" style="max-width:150px;" />
</div>
