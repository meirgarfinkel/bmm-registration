<?php
defined( 'ABSPATH' ) || exit;

class BMM_Form_Editor {

	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post_bmm_reg_form', [ self::class, 'save_meta' ], 10, 2 );
		add_action( 'admin_head', [ self::class, 'inject_status_in_title' ] );
		add_filter( 'post_updated_messages', [ self::class, 'custom_messages' ] );
		add_filter( 'post_row_actions', [ self::class, 'add_preview_row_action' ], 10, 2 );
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'bmm_form_settings',
			__( 'Form Settings', 'bmm-registration' ),
			[ self::class, 'render_settings_meta_box' ],
			'bmm_reg_form',
			'normal',
			'high'
		);

		add_meta_box(
			'bmm_form_link',
			__( 'Public Form Link', 'bmm-registration' ),
			[ self::class, 'render_link_meta_box' ],
			'bmm_reg_form',
			'side',
			'default'
		);
	}

	public static function render_settings_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'bmm_save_form_meta', 'bmm_form_meta_nonce' );
		try {
			$config = new BMM_Form_Config( $post->ID );
		} catch ( \Exception $e ) {
			$config = null;
		}
		require BMM_REG_DIR . 'admin/views/form-editor.php';
	}

	public static function render_link_meta_box( \WP_Post $post ): void {
		$url = $post->post_name ? add_query_arg( 'bmm_form', $post->post_name, home_url( '/' ) ) : '';

		if ( $post->post_status === 'published' ) {
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a></p>';
			echo '<button type="button" class="button" onclick="navigator.clipboard.writeText(\'' . esc_js( $url ) . '\')">' . esc_html__( 'Copy Link', 'bmm-registration' ) . '</button>';
		} elseif ( $post->post_status === 'draft' && $url ) {
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Preview (admin only)', 'bmm-registration' ) . '</a></p>';
		} elseif ( $post->post_status === 'archived' ) {
			echo '<p>' . esc_html__( 'This form is archived (closed to new registrations).', 'bmm-registration' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Save the form first to get a preview link.', 'bmm-registration' ) . '</p>';
		}
	}

	public static function add_preview_row_action( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== 'bmm_reg_form' || $post->post_status !== 'draft' ) {
			return $actions;
		}
		if ( ! $post->post_name || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		$url = add_query_arg( 'bmm_form', $post->post_name, home_url( '/' ) );
		$actions['bmm_preview'] = '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Preview', 'bmm-registration' ) . '</a>';
		return $actions;
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if (
			! isset( $_POST['bmm_form_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmm_form_meta_nonce'] ) ), 'bmm_save_form_meta' ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$fields = [
			'membership_price'          => 'intval',
			'membership_included_men'   => 'intval',
			'membership_included_women' => 'intval',
			'extra_seat_price'          => 'intval',
			'hk_months'                 => 'intval',
			'ragil_tashlumim'           => 'intval',
		];

		foreach ( $fields as $key => $sanitize ) {
			$val = isset( $_POST[ "bmm_form_{$key}" ] ) ? $sanitize( $_POST[ "bmm_form_{$key}" ] ) : 0;
			update_post_meta( $post_id, "_bmm_form_{$key}", $val );
		}

		// Payment options
		$payment_options = sanitize_key( $_POST['bmm_form_payment_options'] ?? 'both' );
		if ( ! in_array( $payment_options, [ 'ragil', 'hk', 'both' ], true ) ) {
			$payment_options = 'both';
		}
		update_post_meta( $post_id, '_bmm_form_payment_options', $payment_options );

		// Credentials
		update_post_meta( $post_id, '_bmm_form_mosad',     sanitize_text_field( $_POST['bmm_form_mosad'] ?? '' ) );
		update_post_meta( $post_id, '_bmm_form_api_valid', sanitize_text_field( $_POST['bmm_form_api_valid'] ?? '' ) );

		// Sponsorships (repeater)
		$sponsorships = [];
		$s_ids     = array_map( 'sanitize_key',        (array) ( $_POST['bmm_sponsorship_id']      ?? [] ) );
		$s_labels  = array_map( 'sanitize_text_field', (array) ( $_POST['bmm_sponsorship_label']   ?? [] ) );
		$s_amounts = array_map( 'intval',               (array) ( $_POST['bmm_sponsorship_amount']  ?? [] ) );
		$s_enabled = (array) ( $_POST['bmm_sponsorship_enabled'] ?? [] );

		foreach ( $s_ids as $i => $id ) {
			if ( $id ) {
				$sponsorships[] = [
					'id'      => $id,
					'label'   => $s_labels[ $i ] ?? '',
					'amount'  => $s_amounts[ $i ] ?? 0,
					'enabled' => in_array( $i, array_map( 'intval', $s_enabled ), true ),
				];
			}
		}

		update_post_meta( $post_id, '_bmm_form_sponsorships', wp_json_encode( $sponsorships ) );
	}

	public static function inject_status_in_title(): void {
		global $post;
		if ( ! $post || $post->post_type !== 'bmm_reg_form' ) {
			return;
		}
		// Show current custom status in the status dropdown
		$status = $post->post_status;
		if ( in_array( $status, [ 'published', 'archived' ], true ) ) {
			$label = ucfirst( $status );
			echo "<script>
			jQuery(function($){
				$('#post-status-display').text('" . esc_js( $label ) . "');
				$('select#post_status').append('<option value=\"{$status}\" selected>{$label}</option>');
			});
			</script>";
		}
	}

	public static function custom_messages( array $messages ): array {
		$messages['bmm_reg_form'] = [
			0  => '',
			1  => __( 'Form updated.', 'bmm-registration' ),
			4  => __( 'Form updated.', 'bmm-registration' ),
			6  => __( 'Form published.', 'bmm-registration' ),
			7  => __( 'Form saved.', 'bmm-registration' ),
			10 => __( 'Form draft updated.', 'bmm-registration' ),
		];
		return $messages;
	}
}
