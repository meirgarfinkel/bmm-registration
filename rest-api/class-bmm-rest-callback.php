<?php
defined( 'ABSPATH' ) || exit;

class BMM_REST_Callback extends \WP_REST_Controller {

	protected $namespace = 'bmm/v1';
	protected $rest_base = 'nedarim-callback';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$handler = new BMM_Callback_Handler();
		return $handler->handle( $request );
	}
}
