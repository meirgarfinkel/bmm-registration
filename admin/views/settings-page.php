<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php esc_html_e( 'BMM Registration – Settings', 'bmm-registration' ); ?></h1>
	<form method="post">
		<?php wp_nonce_field( 'bmm_save_settings', 'bmm_settings_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="mosad"><?php esc_html_e( 'Nedarim Plus Institution ID (Mosad)', 'bmm-registration' ); ?></label></th>
				<td>
					<input type="text" id="mosad" name="mosad" value="<?php echo esc_attr( $settings['mosad'] ?? '' ); ?>" class="regular-text" maxlength="7" />
					<p class="description"><?php esc_html_e( '7-digit institution identifier from Nedarim Plus.', 'bmm-registration' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="api_valid"><?php esc_html_e( 'API Verification Code (ApiValid)', 'bmm-registration' ); ?></label></th>
				<td>
					<input type="text" id="api_valid" name="api_valid" value="<?php echo esc_attr( $settings['api_valid'] ?? '' ); ?>" class="regular-text" maxlength="10" />
					<p class="description"><?php esc_html_e( 'Used for the payment iframe. Request from Nedarim Plus.', 'bmm-registration' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="api_password"><?php esc_html_e( 'API Password (ApiPassword)', 'bmm-registration' ); ?></label></th>
				<td>
					<input type="password" id="api_password" name="api_password" value="<?php echo esc_attr( $settings['api_password'] ?? '' ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Used for transaction history and export APIs only. Keep confidential.', 'bmm-registration' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Settings', 'bmm-registration' ) ); ?>
	</form>
</div>
