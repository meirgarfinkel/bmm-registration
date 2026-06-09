<?php
/**
 * Standalone page template for BMM registration forms.
 * Loaded via template_include when the bmm_form query var is present.
 *
 * Theme-aware rendering:
 * - Classic theme: get_header() / get_footer() load header.php / footer.php.
 * - Block (FSE) theme: those files don't exist, so get_header() would emit
 *   WordPress's bare theme-compat header (site title + tagline, no logo).
 *   Instead we build the document ourselves and render the block 'header' and
 *   'footer' template parts, which contain the real logo and styling.
 */
defined( 'ABSPATH' ) || exit;
global $bmm_current_form_id, $bmm_diagnostic_message;

/**
 * Output the inner form (or a diagnostic / not-found message).
 */
if ( ! function_exists( 'bmm_render_form_body' ) ) {
	function bmm_render_form_body(): void {
		global $bmm_current_form_id, $bmm_diagnostic_message;
		echo '<div id="bmm-form-page" class="bmm-form-page" style="max-width:800px;margin:2rem auto;padding:0 1rem;">';
		if ( ! empty( $bmm_diagnostic_message ) ) {
			echo '<div style="background:#fcf2f2;border:1px solid #e0b4b4;color:#9f3a38;padding:12px 16px;border-radius:4px;">'
				. esc_html( $bmm_diagnostic_message ) . '</div>';
		} elseif ( ! empty( $bmm_current_form_id ) ) {
			echo BMM_Form_Renderer::render( (int) $bmm_current_form_id );
		} else {
			echo '<p>' . esc_html__( 'Registration form not found.', 'bmm-registration' ) . '</p>';
		}
		echo '</div>';
	}
}

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) :
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php echo do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ); ?>
	<main class="wp-block-group">
		<?php bmm_render_form_body(); ?>
	</main>
	<?php echo do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
<?php
else :
	get_header();
	bmm_render_form_body();
	get_footer();
endif;
