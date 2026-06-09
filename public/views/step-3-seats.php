<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Membership & Seat Reservations', 'bmm-registration' ); ?></h3>

<!-- Membership (mutually exclusive with guest seats) -->
<div class="bmm-field bmm-membership-field">
	<label class="bmm-checkbox">
		<input type="checkbox" name="wants_membership" id="bmm_wants_membership" value="1" />
		<span>
			<?php
			printf(
				/* translators: %s = price in NIS */
				esc_html__( 'Annual Membership – ₪%s', 'bmm-registration' ),
				'<strong id="bmm-membership-price"></strong>'
			);
			?>
		</span>
	</label>
	<p class="bmm-membership-includes description" id="bmm-membership-includes-text"></p>
</div>

<!-- Guest Seats (mutually exclusive with membership) -->
<div class="bmm-field bmm-guest-field" id="bmm-guest-field">
	<label class="bmm-checkbox">
		<input type="checkbox" name="wants_guest_seats" id="bmm_wants_guest_seats" value="1" />
		<span>
			<?php
			printf(
				/* translators: %s = price in NIS */
				esc_html__( 'Guest Seats – ₪%s per seat', 'bmm-registration' ),
				'<strong id="bmm-guest-seat-price"></strong>'
			);
			?>
		</span>
	</label>
	<p class="description" style="margin-left:1.6rem;"><?php esc_html_e( 'No membership. Every seat charged at the guest rate — no seats included.', 'bmm-registration' ); ?></p>
</div>

<!-- Same for all toggle -->
<div class="bmm-seats-header">
	<h4>
		<?php esc_html_e( 'Seat Reservations', 'bmm-registration' ); ?>
		<span class="bmm-seat-price-note" id="bmm-seat-price-note"></span>
	</h4>
	<label class="bmm-toggle" id="bmm-same-for-all-label">
		<input type="checkbox" id="bmm_same_for_all" />
		<span class="bmm-toggle__track"></span>
		<span class="bmm-toggle__label"><?php esc_html_e( 'Same for all davenings', 'bmm-registration' ); ?></span>
	</label>
</div>

<!-- "Same for all" row (shown when toggle is on) -->
<div class="bmm-seats-same" id="bmm-seats-same-row" hidden>
	<div class="bmm-seat-row bmm-seat-row--same">
		<span class="bmm-seat-row__label"><?php esc_html_e( 'All Davenings', 'bmm-registration' ); ?></span>
		<div class="bmm-seat-inputs">
			<label>
				<span><?php esc_html_e( "Men's seats", 'bmm-registration' ); ?></span>
				<input type="number" id="bmm_same_men" min="0" value="0" class="bmm-seat-input" />
			</label>
			<label>
				<span><?php esc_html_e( "Women's seats", 'bmm-registration' ); ?></span>
				<input type="number" id="bmm_same_women" min="0" value="0" class="bmm-seat-input" />
			</label>
		</div>
	</div>
</div>

<!-- Per-davening grid (shown when toggle is off) -->
<div class="bmm-seats-grid" id="bmm-seats-grid">
	<div class="bmm-seats-grid__header">
		<span></span>
		<span><?php esc_html_e( "Men's Seats", 'bmm-registration' ); ?></span>
		<span><?php esc_html_e( "Women's Seats", 'bmm-registration' ); ?></span>
	</div>

	<?php foreach ( BMM_Pricing::DAVENINGS as $key => $label ) : ?>
	<div class="bmm-seat-row" data-davening="<?php echo esc_attr( $key ); ?>">
		<span class="bmm-seat-row__label"><?php echo esc_html( $label ); ?></span>
		<input type="number" name="seats_men[<?php echo esc_attr( $key ); ?>]"
			class="bmm-seat-input bmm-seat-men" min="0" value="0"
			aria-label="<?php echo esc_attr( $label . ' – ' . __( "Men's Seats", 'bmm-registration' ) ); ?>" />
		<input type="number" name="seats_women[<?php echo esc_attr( $key ); ?>]"
			class="bmm-seat-input bmm-seat-women" min="0" value="0"
			aria-label="<?php echo esc_attr( $label . ' – ' . __( "Women's Seats", 'bmm-registration' ) ); ?>" />
	</div>
	<?php endforeach; ?>
</div>

<!-- Live pricing preview -->
<div class="bmm-pricing-preview" id="bmm-pricing-preview">
	<div class="bmm-pricing-preview__row" id="bmm-preview-membership" hidden>
		<span><?php esc_html_e( 'Membership', 'bmm-registration' ); ?></span>
		<span id="bmm-preview-membership-amount"></span>
	</div>
	<div class="bmm-pricing-preview__row" id="bmm-preview-extra-men" hidden>
		<span id="bmm-preview-extra-men-label"></span>
		<span id="bmm-preview-extra-men-amount"></span>
	</div>
	<div class="bmm-pricing-preview__row" id="bmm-preview-extra-women" hidden>
		<span id="bmm-preview-extra-women-label"></span>
		<span id="bmm-preview-extra-women-amount"></span>
	</div>
	<div class="bmm-pricing-preview__row" id="bmm-preview-guest-men" hidden>
		<span id="bmm-preview-guest-men-label"></span>
		<span id="bmm-preview-guest-men-amount"></span>
	</div>
	<div class="bmm-pricing-preview__row" id="bmm-preview-guest-women" hidden>
		<span id="bmm-preview-guest-women-label"></span>
		<span id="bmm-preview-guest-women-amount"></span>
	</div>
	<div class="bmm-pricing-preview__row bmm-pricing-preview__subtotal" id="bmm-preview-subtotal" hidden>
		<span><strong><?php esc_html_e( 'Subtotal', 'bmm-registration' ); ?></strong></span>
		<strong id="bmm-preview-subtotal-amount"></strong>
	</div>
</div>
