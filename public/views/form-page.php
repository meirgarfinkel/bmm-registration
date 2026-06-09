<?php
/**
 * Standalone page template for BMM registration forms.
 * Loaded via template_include when the bmm_form query var is present.
 */
defined( 'ABSPATH' ) || exit;
global $bmm_current_form_id, $bmm_diagnostic_message;
get_header();
?>
<div id="bmm-form-page" class="bmm-form-page" style="max-width:800px;margin:2rem auto;padding:0 1rem;">
	<?php
	if ( ! empty( $bmm_diagnostic_message ) ) {
		echo '<div style="background:#fcf2f2;border:1px solid #e0b4b4;color:#9f3a38;padding:12px 16px;border-radius:4px;">'
			. esc_html( $bmm_diagnostic_message )
			. '</div>';
	} elseif ( ! empty( $bmm_current_form_id ) ) {
		echo BMM_Form_Renderer::render( (int) $bmm_current_form_id );
	} else {
		echo '<p>' . esc_html__( 'Registration form not found.', 'bmm-registration' ) . '</p>';
	}
	?>
</div>
<?php
get_footer();
