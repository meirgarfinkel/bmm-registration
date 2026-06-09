<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Hebrew Names', 'bmm-registration' ); ?></h3>

<div class="bmm-field">
	<label for="bmm_hebrew_name"><?php esc_html_e( 'Your Hebrew Name', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
	<input type="text" id="bmm_hebrew_name" name="hebrew_name" dir="rtl"
		placeholder="פלוני אלמוני בן אבא" required />
</div>

<div class="bmm-field">
	<label><?php esc_html_e( 'Tribe', 'bmm-registration' ); ?> <span class="bmm-required">*</span></label>
	<div class="bmm-radio-group" role="radiogroup">
		<label class="bmm-radio">
			<input type="radio" name="tribe" value="kohen" />
			<span dir="rtl">כהן</span>
		</label>
		<label class="bmm-radio">
			<input type="radio" name="tribe" value="levi" />
			<span dir="rtl">לוי</span>
		</label>
		<label class="bmm-radio">
			<input type="radio" name="tribe" value="yisrael" checked />
			<span dir="rtl">ישראל</span>
		</label>
	</div>
</div>

<div class="bmm-field">
	<label for="bmm_wife_hebrew_name"><?php esc_html_e( "Wife's Hebrew Name", 'bmm-registration' ); ?> <span class="bmm-optional"><?php esc_html_e( '(optional)', 'bmm-registration' ); ?></span></label>
	<input type="text" id="bmm_wife_hebrew_name" name="wife_hebrew_name" dir="rtl"
		placeholder="פלונית בת אבא" />
</div>

<div class="bmm-field" id="bmm-children-section">
	<label><?php esc_html_e( "Children's Hebrew Names", 'bmm-registration' ); ?> <span class="bmm-optional"><?php esc_html_e( '(optional)', 'bmm-registration' ); ?></span></label>
	<div id="bmm-children-list">
		<div class="bmm-child-row">
			<input type="text" name="children_hebrew_names[]" dir="rtl"
				placeholder="<?php esc_attr_e( 'Child Hebrew name', 'bmm-registration' ); ?>" />
			<button type="button" class="bmm-btn-icon bmm-remove-child" aria-label="<?php esc_attr_e( 'Remove', 'bmm-registration' ); ?>" hidden>&times;</button>
		</div>
	</div>
	<button type="button" class="bmm-btn bmm-btn--secondary bmm-btn--sm" id="bmm-add-child">
		+ <?php esc_html_e( 'Add Child', 'bmm-registration' ); ?>
	</button>
</div>
