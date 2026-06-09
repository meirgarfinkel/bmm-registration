<?php
defined( 'ABSPATH' ) || exit;

class BMM_CSV_Export {

	public static function handle_export_request(): void {
		if (
			! isset( $_POST['bmm_export_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_export_nonce'] ) ), 'bmm_export_csv' ) ||
			! current_user_can( 'manage_options' )
		) {
			wp_die( esc_html__( 'Unauthorized', 'bmm-registration' ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

		$query_args = [
			'post_type'      => 'bmm_submission',
			'post_status'    => $status ?: [ 'pending', 'completed', 'failed' ],
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $form_id ) {
			$query_args['post_parent'] = $form_id;
		}

		$submissions = get_posts( $query_args );

		$filename = 'bmm-submissions-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, self::get_headers() );

		foreach ( $submissions as $post ) {
			fputcsv( $output, self::get_row( $post ) );
		}

		fclose( $output );
		exit;
	}

	private static function get_headers(): array {
		return [
			'Submission ID', 'Date', 'Payment Status', 'Form Name',
			'First Name', 'Last Name', 'Email', 'Phone', 'City', 'Address', 'Israeli ID',
			'Hebrew Name', 'Tribe', 'Wife Hebrew Name', 'Children Hebrew Names',
			'Membership Purchased',
			'Seats Men - RH Night 1', 'Seats Men - RH Day 1', 'Seats Men - RH Night 2',
			'Seats Men - RH Day 2', 'Seats Men - YK Night', 'Seats Men - YK Day',
			'Seats Women - RH Night 1', 'Seats Women - RH Day 1', 'Seats Women - RH Night 2',
			'Seats Women - RH Day 2', 'Seats Women - YK Night', 'Seats Women - YK Day',
			'Sponsorships Selected', 'Notes', 'Payment Type',
			'Membership Fee (NIS)', 'Extra Men Seats Fee (NIS)', 'Extra Women Seats Fee (NIS)',
			'Sponsorships Fee (NIS)', 'Total Charged (NIS)',
			'Nedarim Transaction ID', 'Standing Order ID', 'Approval Number', 'Card Last 4',
			'Payment Completed At',
		];
	}

	private static function get_row( \WP_Post $post ): array {
		$meta = BMM_Submission::get_meta( $post->ID );

		$form_name = '';
		if ( $post->post_parent ) {
			$form = get_post( $post->post_parent );
			$form_name = $form ? $form->post_title : '';
		}

		// Resolve sponsorship labels from selected IDs
		$sponsorship_labels = [];
		if ( $post->post_parent && ! empty( $meta['sponsorships_selected'] ) ) {
			try {
				$form_config = new BMM_Form_Config( $post->post_parent );
				$map         = array_column( $form_config->enabled_sponsorships(), 'label', 'id' );
				foreach ( $meta['sponsorships_selected'] as $id ) {
					if ( isset( $map[ $id ] ) ) {
						$sponsorship_labels[] = $map[ $id ];
					}
				}
			} catch ( \Exception $e ) {
				// form may be deleted
			}
		}

		$seats_men   = $meta['seats_men'] ?: array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), 0 );
		$seats_women = $meta['seats_women'] ?: array_fill_keys( array_keys( BMM_Pricing::DAVENINGS ), 0 );

		$dav_keys = array_keys( BMM_Pricing::DAVENINGS );

		$children = is_array( $meta['children_hebrew_names'] )
			? implode( '; ', $meta['children_hebrew_names'] )
			: '';

		return [
			$post->ID,
			$post->post_date,
			$post->post_status,
			$form_name,
			$meta['first_name'],
			$meta['last_name'],
			$meta['email'],
			$meta['phone'],
			$meta['city'],
			$meta['address'],
			$meta['zeout'],
			$meta['hebrew_name'],
			$meta['tribe'],
			$meta['wife_hebrew_name'],
			$children,
			$meta['wants_membership'] ? 'Yes' : 'No',
			// 6 men seats
			$seats_men[ $dav_keys[0] ] ?? 0, $seats_men[ $dav_keys[1] ] ?? 0,
			$seats_men[ $dav_keys[2] ] ?? 0, $seats_men[ $dav_keys[3] ] ?? 0,
			$seats_men[ $dav_keys[4] ] ?? 0, $seats_men[ $dav_keys[5] ] ?? 0,
			// 6 women seats
			$seats_women[ $dav_keys[0] ] ?? 0, $seats_women[ $dav_keys[1] ] ?? 0,
			$seats_women[ $dav_keys[2] ] ?? 0, $seats_women[ $dav_keys[3] ] ?? 0,
			$seats_women[ $dav_keys[4] ] ?? 0, $seats_women[ $dav_keys[5] ] ?? 0,
			implode( '; ', $sponsorship_labels ),
			$meta['notes'],
			$meta['payment_type'],
			$meta['price_membership'],
			$meta['price_extra_men'],
			$meta['price_extra_women'],
			$meta['price_sponsorships'],
			$meta['price_total'],
			$meta['nedarim_transaction_id'],
			$meta['nedarim_keva_id'],
			$meta['nedarim_confirmation'],
			$meta['nedarim_last_num'],
			$meta['payment_completed_at'],
		];
	}
}
