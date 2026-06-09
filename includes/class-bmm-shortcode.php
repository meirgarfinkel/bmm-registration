<?php
defined( 'ABSPATH' ) || exit;

class BMM_Shortcode {

	public static function init(): void {
		add_shortcode( 'bmm_registration', [ self::class, 'render' ] );
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
