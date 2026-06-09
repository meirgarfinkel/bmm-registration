<?php defined( 'ABSPATH' ) || exit; ?>
<div class="bmm-registration-wrap" id="bmm-registration" data-form-id="<?php echo esc_attr( $form->post_id ); ?>">

	<h2 class="bmm-form-title"><?php echo esc_html( $form->title ); ?></h2>

	<!-- Step indicator -->
	<div class="bmm-steps" role="navigation" aria-label="<?php esc_attr_e( 'Form steps', 'bmm-registration' ); ?>">
		<?php foreach ( $steps as $num => $label ) : ?>
		<div class="bmm-step" data-step="<?php echo esc_attr( $num ); ?>">
			<span class="bmm-step__number"><?php echo esc_html( $num ); ?></span>
			<span class="bmm-step__label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Status messages -->
	<div class="bmm-notice bmm-notice--error" id="bmm-error" hidden></div>
	<div class="bmm-notice bmm-notice--success" id="bmm-success" hidden></div>

	<!-- Steps -->
	<div class="bmm-form-steps">
		<div class="bmm-form-step" data-step="1"><?php require __DIR__ . '/step-1-personal.php'; ?></div>
		<div class="bmm-form-step" data-step="2"><?php require __DIR__ . '/step-2-hebrew-names.php'; ?></div>
		<div class="bmm-form-step" data-step="3"><?php require __DIR__ . '/step-3-seats.php'; ?></div>
		<div class="bmm-form-step" data-step="4"><?php require __DIR__ . '/step-4-sponsorships.php'; ?></div>
		<div class="bmm-form-step" data-step="5"><?php require __DIR__ . '/step-5-notes-payment-type.php'; ?></div>
		<div class="bmm-form-step" data-step="6"><?php require __DIR__ . '/step-6-summary-payment.php'; ?></div>
	</div>

	<!-- Navigation -->
	<div class="bmm-form-nav">
		<button type="button" class="bmm-btn bmm-btn--secondary" id="bmm-prev" hidden>
			&larr; <?php esc_html_e( 'Back', 'bmm-registration' ); ?>
		</button>
		<button type="button" class="bmm-btn bmm-btn--primary" id="bmm-next">
			<?php esc_html_e( 'Next', 'bmm-registration' ); ?> &rarr;
		</button>
		<button type="button" class="bmm-btn bmm-btn--primary" id="bmm-submit-btn" hidden>
			<?php esc_html_e( 'Proceed to Payment', 'bmm-registration' ); ?>
		</button>
	</div>

</div>
