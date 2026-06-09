<?php
defined( 'ABSPATH' ) || exit;

class BMM_Form_Config {

	public int    $post_id;
	public string $title;
	public string $slug;
	public string $status;
	public int    $membership_price;
	public int    $membership_included_men;
	public int    $membership_included_women;
	public int    $extra_seat_price;
	public array  $sponsorships;
	public string $payment_options;
	public int    $hk_months;
	public int    $ragil_tashlumim;
	public string $mosad;
	public string $api_valid;

	public static function default_sponsorships(): array {
		return [
			[ 'id' => 'kiddush',       'label' => 'Kiddush Fund',      'amount' => 350, 'enabled' => true ],
			[ 'id' => 'avos_ubanim',   'label' => 'Avos Ubanim Fund',  'amount' => 300, 'enabled' => true ],
			[ 'id' => 'shalosh_seudos','label' => 'Shalosh Seudos Fund','amount' => 250, 'enabled' => true ],
		];
	}

	public function __construct( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'bmm_reg_form' ) {
			throw new \InvalidArgumentException( "Invalid bmm_reg_form post ID: $post_id" );
		}

		$this->post_id  = $post_id;
		$this->title    = $post->post_title;
		$this->slug     = $post->post_name;
		$this->status   = $post->post_status;

		$m = fn( string $key ) => get_post_meta( $post_id, "_bmm_form_{$key}", true );

		$this->membership_price          = (int) ( $m( 'membership_price' ) ?: 0 );
		$this->membership_included_men   = (int) ( $m( 'membership_included_men' ) ?: 0 );
		$this->membership_included_women = (int) ( $m( 'membership_included_women' ) ?: 0 );
		$this->extra_seat_price          = (int) ( $m( 'extra_seat_price' ) ?: 0 );
		$this->payment_options           = $m( 'payment_options' ) ?: 'both';
		$this->hk_months                 = (int) ( $m( 'hk_months' ) ?: 0 );
		$this->ragil_tashlumim           = (int) ( $m( 'ragil_tashlumim' ) ?: 1 );

		$raw_sponsorships   = $m( 'sponsorships' );
		$this->sponsorships = $raw_sponsorships
			? (array) json_decode( $raw_sponsorships, true )
			: self::default_sponsorships();

		// Per-form credentials fall back to global settings
		$this->mosad     = $m( 'mosad' ) ?: BMM_Settings::get( 'mosad' );
		$this->api_valid = $m( 'api_valid' ) ?: BMM_Settings::get( 'api_valid' );
	}

	public function enabled_sponsorships(): array {
		return array_values( array_filter( $this->sponsorships, fn( $s ) => ! empty( $s['enabled'] ) ) );
	}

	public function is_published(): bool {
		return $this->status === 'published';
	}

	public static function get_by_slug( string $slug ): ?\WP_Post {
		$posts = get_posts( [
			'post_type'      => 'bmm_reg_form',
			'post_status'    => [ 'published', 'archived', 'draft' ],
			'name'           => $slug,
			'posts_per_page' => 1,
			'fields'         => 'all',
		] );
		return $posts[0] ?? null;
	}

	public function get_public_url(): string {
		return add_query_arg( 'bmm_form', $this->slug, home_url( '/' ) );
	}
}
