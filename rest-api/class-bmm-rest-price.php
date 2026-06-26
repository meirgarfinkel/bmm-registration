<?php
defined( 'ABSPATH' ) || exit;

class BMM_REST_Price extends \WP_REST_Controller {

	protected $namespace = 'bmm/v1';
	protected $rest_base = 'calculate-price';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'calculate' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'form_id'          => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
				'wants_membership'  => [ 'type' => 'boolean', 'default' => false ],
				'wants_guest_seats' => [ 'type' => 'boolean', 'default' => false ],
				'seats_men'         => [ 'type' => 'object', 'default' => [] ],
				'seats_women'       => [ 'type' => 'object', 'default' => [] ],
				'sponsorship_ids'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
				'sponsorship_other' => [ 'type' => 'number', 'minimum' => 0, 'default' => 0 ],
			],
		] );
	}

	public function calculate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$form_id = (int) $request->get_param( 'form_id' );

		try {
			$form = new BMM_Form_Config( $form_id );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'invalid_form', __( 'Form not found.', 'bmm-registration' ), [ 'status' => 404 ] );
		}

		// Reject only archived forms. Draft forms are allowed so that admin
		// preview pages can still calculate live pricing.
		if ( $form->status === 'archived' ) {
			return new \WP_Error( 'form_archived', __( 'Form is not available.', 'bmm-registration' ), [ 'status' => 403 ] );
		}

		$data    = $request->get_params();
		$pricing = BMM_Pricing::calculate( $form, $data );

		return new \WP_REST_Response( $pricing, 200 );
	}
}
