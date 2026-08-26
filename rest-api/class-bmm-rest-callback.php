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

		// Admin-only: simulate a completed Nedarim callback end-to-end without a
		// real payment. Creates a clearly-marked TEST submission, runs the real
		// completion logic, and reports the status transition.
		register_rest_route( $this->namespace, '/simulate-callback', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'simulate' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$handler = new BMM_Callback_Handler();
		return $handler->handle( $request );
	}

	public function simulate( \WP_REST_Request $request ): \WP_REST_Response {
		$form_id = (int) $request->get_param( 'form_id' );

		try {
			$form = new BMM_Form_Config( $form_id );
		} catch ( \Exception $e ) {
			return new \WP_REST_Response( [ 'error' => 'Form not found.' ], 404 );
		}

		// Use whatever real form data the admin submitted with the simulate
		// request (name, seats incl. "same for all davenings", membership choice),
		// falling back to dummy values for anything missing.
		$data = BMM_Submission::build_simulation_data( $request->get_params() );

		$pricing       = BMM_Pricing::calculate( $form, $data );
		$submission_id = BMM_Submission::create_draft( $form_id, $data, $pricing );

		// Mark it clearly as a test so it is never mistaken for a real
		// registration: flag it and prefix the list-table name with "[TEST]".
		update_post_meta( $submission_id, '_bmm_sub_is_simulation', 1 );
		$post = get_post( $submission_id );
		if ( $post ) {
			wp_update_post( [ 'ID' => $submission_id, 'post_title' => '[TEST] ' . $post->post_title ] );
		}

		$status_before = get_post_status( $submission_id );

		// Simulated Nedarim Plus success payload (regular, non-HK transaction).
		$payload = [
			'Param1'        => (string) $submission_id,
			'TransactionId' => 'SIM-' . $submission_id,
			'Confirmation'  => 'SIMTEST',
			'LastNum'       => '0000',
			'Amount'        => (string) $pricing['total'],
		];

		BMM_Submission::complete( $submission_id, $payload, false );

		return new \WP_REST_Response( [
			'simulated'      => true,
			'submission_id'  => $submission_id,
			'status_before'  => $status_before,
			'status_after'   => get_post_status( $submission_id ),
			'transaction_id' => get_post_meta( $submission_id, '_bmm_sub_nedarim_transaction_id', true ),
			'completed_at'   => get_post_meta( $submission_id, '_bmm_sub_payment_completed_at', true ),
			'total'          => $pricing['total'],
			'admin_link'     => admin_url( 'admin.php?page=bmm-submissions&submission_id=' . $submission_id ),
		], 200 );
	}
}
