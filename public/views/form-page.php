<?php
/**
 * Standalone page template for BMM registration forms.
 * Loaded via template_include when the bmm_form query var is present.
 */
defined( 'ABSPATH' ) || exit;
global $bmm_current_form_id;
get_header();
?>
<div id="bmm-form-page" class="bmm-form-page" style="max-width:800px;margin:2rem auto;padding:0 1rem;">
	<?php echo BMM_Form_Renderer::render( (int) $bmm_current_form_id ); ?>
</div>
<?php
get_footer();
