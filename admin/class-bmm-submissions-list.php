<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BMM_Submissions_List extends \WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'submission',
			'plural'   => 'submissions',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'cb'             => '<input type="checkbox" />',
			'name'           => __( 'Name', 'bmm-registration' ),
			'email'          => __( 'Email', 'bmm-registration' ),
			'form'           => __( 'Form', 'bmm-registration' ),
			'men_rh'         => __( "Men's seats (RH)", 'bmm-registration' ),
			'women_rh'       => __( "Women's seats (RH)", 'bmm-registration' ),
			'men_yk'         => __( "Men's seats (YK)", 'bmm-registration' ),
			'women_yk'       => __( "Women's seats (YK)", 'bmm-registration' ),
			'total'          => __( 'Total (₪)', 'bmm-registration' ),
			'payment_type'   => __( 'Payment Type', 'bmm-registration' ),
			'payment_status' => __( 'Payment Status', 'bmm-registration' ),
			'date'           => __( 'Date', 'bmm-registration' ),
		];
	}

	public function get_sortable_columns(): array {
		return [
			'name' => [ 'title', false ],
			'date' => [ 'date', true ],
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'mark_completed' => __( 'Mark as Completed', 'bmm-registration' ),
			'mark_pending'   => __( 'Mark as Pending', 'bmm-registration' ),
			'mark_failed'    => __( 'Mark as Failed', 'bmm-registration' ),
			'delete'         => __( 'Delete (move to Trash)', 'bmm-registration' ),
		];
	}

	public function prepare_items(): void {
		$per_page     = 25;
		$current_page = $this->get_pagenum();
		$status_filter = isset( $_GET['payment_status'] ) ? sanitize_key( $_GET['payment_status'] ) : '';
		$form_filter   = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;

		$args = [
			'post_type'      => 'bmm_submission',
			'post_status'    => $status_filter ?: [ 'bmm_pending', 'completed', 'failed' ],
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $form_filter ) {
			$args['post_parent'] = $form_filter;
		}

		$query      = new \WP_Query( $args );
		$this->items = $query->posts;

		$this->set_pagination_args( [
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	public function column_default( $item, $column_name ): string {
		return '';
	}

	public function column_cb( $item ): string {
		return '<input type="checkbox" name="submission_ids[]" value="' . esc_attr( $item->ID ) . '" />';
	}

	public function column_name( \WP_Post $item ): string {
		$url    = admin_url( 'admin.php?page=bmm-submissions&submission_id=' . $item->ID );
		$name   = esc_html( $item->post_title );
		$actions = [
			'view'   => '<a href="' . esc_url( $url ) . '">' . __( 'View', 'bmm-registration' ) . '</a>',
		];
		return '<a href="' . esc_url( $url ) . '"><strong>' . $name . '</strong></a>' . $this->row_actions( $actions );
	}

	public function column_email( \WP_Post $item ): string {
		return esc_html( get_post_meta( $item->ID, '_bmm_sub_email', true ) );
	}

	public function column_form( \WP_Post $item ): string {
		if ( ! $item->post_parent ) {
			return '—';
		}
		$form = get_post( $item->post_parent );
		return $form ? esc_html( $form->post_title ) : '—';
	}

	/** Decode a stored per-davening seats meta value ('men' or 'women'). */
	private function seats_meta( int $post_id, string $gender ): array {
		$raw = get_post_meta( $post_id, "_bmm_sub_seats_{$gender}", true );
		$decoded = $raw ? json_decode( $raw, true ) : [];
		return is_array( $decoded ) ? $decoded : [];
	}

	public function column_men_rh( \WP_Post $item ): string {
		return (string) BMM_Pricing::seats_for_holiday( $this->seats_meta( $item->ID, 'men' ), 'rh' );
	}

	public function column_women_rh( \WP_Post $item ): string {
		return (string) BMM_Pricing::seats_for_holiday( $this->seats_meta( $item->ID, 'women' ), 'rh' );
	}

	public function column_men_yk( \WP_Post $item ): string {
		return (string) BMM_Pricing::seats_for_holiday( $this->seats_meta( $item->ID, 'men' ), 'yk' );
	}

	public function column_women_yk( \WP_Post $item ): string {
		return (string) BMM_Pricing::seats_for_holiday( $this->seats_meta( $item->ID, 'women' ), 'yk' );
	}

	public function column_total( \WP_Post $item ): string {
		$total = get_post_meta( $item->ID, '_bmm_sub_price_total', true );
		return $total !== '' ? '₪' . esc_html( $total ) : '—';
	}

	public function column_payment_type( \WP_Post $item ): string {
		$type = get_post_meta( $item->ID, '_bmm_sub_payment_type', true );
		if ( $type === 'Tashlumim' ) {
			$n = (int) get_post_meta( $item->ID, '_bmm_sub_tashlumim', true );
			return $n > 1
				? sprintf( /* translators: %d = number of payments */ __( 'Tashlumim (%d)', 'bmm-registration' ), $n )
				: __( 'Tashlumim', 'bmm-registration' );
		}
		return __( 'Pay in full', 'bmm-registration' );
	}

	public function column_payment_status( \WP_Post $item ): string {
		$status_labels = [
			'bmm_pending' => '<span class="bmm-status bmm-status--pending">'   . esc_html__( 'Pending', 'bmm-registration' )   . '</span>',
			'completed'   => '<span class="bmm-status bmm-status--completed">' . esc_html__( 'Completed', 'bmm-registration' ) . '</span>',
			'failed'      => '<span class="bmm-status bmm-status--failed">'    . esc_html__( 'Failed', 'bmm-registration' )    . '</span>',
		];
		return $status_labels[ $item->post_status ] ?? esc_html( $item->post_status );
	}

	public function column_date( \WP_Post $item ): string {
		return esc_html( get_the_date( 'Y-m-d H:i', $item->ID ) );
	}

	protected function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		$forms = get_posts( [
			'post_type'      => 'bmm_reg_form',
			'post_status'    => [ 'published', 'archived', 'draft' ],
			'posts_per_page' => -1,
		] );

		$current_form   = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
		$current_status = isset( $_GET['payment_status'] ) ? sanitize_key( $_GET['payment_status'] ) : '';

		echo '<div class="alignleft actions">';

		// Form filter
		echo '<select name="form_id"><option value="">' . esc_html__( 'All Forms', 'bmm-registration' ) . '</option>';
		foreach ( $forms as $form ) {
			echo '<option value="' . esc_attr( $form->ID ) . '"' . selected( $current_form, $form->ID, false ) . '>' . esc_html( $form->post_title ) . '</option>';
		}
		echo '</select>';

		// Status filter
		echo '<select name="payment_status"><option value="">' . esc_html__( 'All Statuses', 'bmm-registration' ) . '</option>';
		foreach ( [ 'bmm_pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed' ] as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $current_status, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		submit_button( __( 'Filter', 'bmm-registration' ), 'secondary', 'filter_action', false );
		echo '</div>';

		// Export button
		echo '<div class="alignleft actions">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'bmm_export_csv', 'bmm_export_nonce' );
		echo '<input type="hidden" name="action" value="bmm_export_csv">';
		echo '<input type="hidden" name="form_id" value="' . esc_attr( $current_form ) . '">';
		echo '<input type="hidden" name="status" value="' . esc_attr( $current_status ) . '">';
		submit_button( __( 'Export CSV', 'bmm-registration' ), 'secondary', 'export', false );
		echo '</form>';
		echo '</div>';
	}
}
