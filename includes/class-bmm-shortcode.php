<?php
defined( 'ABSPATH' ) || exit;

class BMM_Shortcode {

	public static function init(): void {
		add_shortcode( 'bmm_registration', [ self::class, 'render' ] );
		// Enqueue the form assets early (so CSS lands in <head>) whenever the
		// current singular page contains the shortcode. render() also enqueues,
		// but that runs during the_content — too late for header styles.
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
	}

	public static function maybe_enqueue(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! has_shortcode( $post->post_content, 'bmm_registration' ) ) {
			return;
		}
		// Pull the form slug/ID out of the shortcode so we can localize config.
		if ( preg_match( '/\[bmm_registration[^\]]*form=["\']?([^"\'\]\s]+)/', $post->post_content, $m ) ) {
			$form_post = BMM_Form_Config::get_by_slug( sanitize_title( $m[1] ) );
			if ( $form_post ) {
				try {
					BMM_Form_Renderer::enqueue_assets( new BMM_Form_Config( $form_post->ID ) );
				} catch ( \Exception $e ) {
					// Fall through — render() will handle the user-facing message.
				}
			}
		}
	}

	public static function render( array $atts ): string {
		$atts = shortcode_atts( [ 'form' => '' ], $atts, 'bmm_registration' );
		$slug = sanitize_title( $atts['form'] );

		if ( ! $slug ) {
			return '<p>' . esc_html__( 'Registration form not specified.', 'bmm-registration' ) . '</p>';
		}

		$form_post = BMM_Form_Config::get_by_slug( $slug );
		if ( ! $form_post ) {
			return '<p>' . esc_html__( 'Registration form not found.', 'bmm-registration' ) . '</p>';
		}

		return BMM_Form_Renderer::render( $form_post->ID );
	}
}
