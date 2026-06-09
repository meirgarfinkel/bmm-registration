<?php defined( 'ABSPATH' ) || exit; ?>
<h3><?php esc_html_e( 'Summary & Payment', 'bmm-registration' ); ?></h3>

<!-- Itemized summary (populated by JS) -->
<div class="bmm-summary" id="bmm-summary">
	<table class="bmm-summary__table">
		<tbody id="bmm-summary-rows">
			<!-- Rows injected by bmm-form.js -->
		</tbody>
		<tfoot>
			<tr class="bmm-summary__total">
				<td><?php esc_html_e( 'Total', 'bmm-registration' ); ?></td>
				<td id="bmm-summary-total">—</td>
			</tr>
		</tfoot>
	</table>
</div>

<!-- Payment processing status -->
<div class="bmm-payment-status" id="bmm-payment-status" hidden>
	<p class="bmm-payment-status__message" id="bmm-payment-status-msg"></p>
</div>

<!-- Nedarim Plus iframe container -->
<div class="bmm-iframe-wrap" id="bmm-iframe-wrap" hidden>
	<p class="description"><?php esc_html_e( 'Enter your credit card details below, then click Pay. The total amount is pre-filled and cannot be changed.', 'bmm-registration' ); ?></p>
	<iframe
		id="bmm-nedarim-iframe"
		src="about:blank"
		style="width:100%;min-height:300px;border:none;"
		title="<?php esc_attr_e( 'Secure Payment', 'bmm-registration' ); ?>"
		aria-label="<?php esc_attr_e( 'Nedarim Plus secure payment form', 'bmm-registration' ); ?>"
	></iframe>
	<div class="bmm-pay-now-wrap" style="text-align:center;margin-top:1rem;">
		<button type="button" class="bmm-btn bmm-btn--primary" id="bmm-pay-now" hidden>
			<?php esc_html_e( 'Pay', 'bmm-registration' ); ?>
		</button>
	</div>
</div>

<!-- Success message (shown after payment completes) -->
<div class="bmm-success" id="bmm-payment-success" hidden>
	<h3><?php esc_html_e( '✓ Registration Complete!', 'bmm-registration' ); ?></h3>
	<p><?php esc_html_e( 'Thank you for your registration. A confirmation will be sent to your email.', 'bmm-registration' ); ?></p>
	<p><?php esc_html_e( 'Confirmation number:', 'bmm-registration' ); ?> <strong id="bmm-confirmation-number"></strong></p>
</div>
