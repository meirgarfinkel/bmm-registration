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
	public string $page_template;

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
		$this->mosad         = $m( 'mosad' ) ?: BMM_Settings::get( 'mosad' );
		$this->api_valid     = $m( 'api_valid' ) ?: BMM_Settings::get( 'api_valid' );
		$this->page_template = $m( 'page_template' ) ?: '';
	}

	public function enabled_sponsorships(): array {
		return array_values( array_filter( $this->sponsorships, fn( $s ) => ! empty( $s['enabled'] ) ) );
	}

	public function is_published(): bool {
		// Accept both our custom 'published' and WP's built-in 'publish' (which
		// the intercept hook will remap, but we handle it here as a safety net).
		return in_array( $this->status, [ 'published', 'publish' ], true );
	}

	/**
	 * Look up a bmm_reg_form post by slug or numeric post ID.
	 * Drafts without a slug yet are accessed via their numeric ID.
	 */
	public static function get_by_slug( string $slug ): ?\WP_Post {
		// Numeric ID fallback — used for drafts that have no slug yet.
		if ( ctype_digit( $slug ) ) {
			$post = get_post( (int) $slug );
			return ( $post && $post->post_type === 'bmm_reg_form' ) ? $post : null;
		}

		$posts = get_posts( [
			'post_type'      => 'bmm_reg_form',
			'post_status'    => [ 'publish', 'published', 'archived', 'draft' ],
			'name'           => $slug,
			'posts_per_page' => 1,
			'fields'         => 'all',
		] );
		return $posts[0] ?? null;
	}

	public function get_public_url(): string {
		if ( $this->slug ) {
			// Clean pretty-URL — works in WP menus, shareable, no query string.
			return home_url( '/register/' . $this->slug . '/' );
		}
		// Unslugged draft (no post_name yet): fall back to query-string by ID.
		return add_query_arg( 'bmm_form', (string) $this->post_id, home_url( '/' ) );
	}
}
