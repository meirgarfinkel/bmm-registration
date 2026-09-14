<?php
defined( 'ABSPATH' ) || exit;
// Variables: $matches (array), $error (string), $completed (int|null),
//            $did_search (bool), $has_creds (bool)
$settings_url = admin_url( 'admin.php?page=bmm-settings' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Reconcile Payments', 'bmm-registration' ); ?></h1>
	<p class="description" style="max-width:720px;">
		<?php esc_html_e( 'Nedarim sends each completion callback only once with no retry, so a lost callback can leave a paid registration on "Pending". This pulls Nedarim\'s cleared-transaction history and proposes matches to your pending submissions. Matching is by amount plus a contact field (phone, Israeli ID, or email), so review each match before completing it.', 'bmm-registration' ); ?>
	</p>

	<?php if ( $completed !== null ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php
			echo esc_html( sprintf(
				/* translators: %d: number of submissions */
				_n( '%d submission marked as Completed.', '%d submissions marked as Completed.', (int) $completed, 'bmm-registration' ),
				(int) $completed
			) );
		?></p></div>
	<?php endif; ?>

	<?php if ( $error !== '' ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $has_creds ) : ?>
		<div class="notice notice-warning"><p><?php
			printf(
				/* translators: %s: settings page URL */
				wp_kses( __( 'Set the Nedarim <strong>Institution ID</strong> and <strong>API Password</strong> in <a href="%s">Settings</a> first. The API Password is separate from the payment code — ask the Nedarim office for it.', 'bmm-registration' ), [ 'strong' => [], 'a' => [ 'href' => [] ] ] ),
				esc_url( $settings_url )
			);
		?></p></div>
	<?php endif; ?>

	<form method="post" style="margin:16px 0;">
		<?php wp_nonce_field( 'bmm_reconcile', 'bmm_reconcile_nonce' ); ?>
		<button type="submit" name="bmm_reconcile_find" value="1" class="button button-primary" <?php disabled( ! $has_creds ); ?>>
			<?php esc_html_e( 'Find matches from Nedarim', 'bmm-registration' ); ?>
		</button>
		<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Pulls the recent transaction history (limited by Nedarim to 20 requests/hour).', 'bmm-registration' ); ?></span>
	</form>

	<?php if ( $did_search && $error === '' ) : ?>
		<?php if ( empty( $matches ) ) : ?>
			<p><strong><?php esc_html_e( 'No matches found.', 'bmm-registration' ); ?></strong>
				<?php esc_html_e( 'No pending submission matched a cleared transaction by amount and a contact field.', 'bmm-registration' ); ?></p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'bmm_reconcile', 'bmm_reconcile_nonce' ); ?>
				<p><?php echo esc_html( sprintf(
					/* translators: %d: number of matches */
					_n( '%d proposed match. Tick the ones to complete, then confirm.', '%d proposed matches. Tick the ones to complete, then confirm.', count( $matches ), 'bmm-registration' ),
					count( $matches )
				) ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" onclick="jQuery('.bmm-recon-cb').prop('checked', this.checked);" /></td>
							<th><?php esc_html_e( 'Pending submission', 'bmm-registration' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'bmm-registration' ); ?></th>
							<th><?php esc_html_e( 'Matched on', 'bmm-registration' ); ?></th>
							<th><?php esc_html_e( 'Nedarim transaction', 'bmm-registration' ); ?></th>
							<th><?php esc_html_e( 'When', 'bmm-registration' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $matches as $m ) :
						$sid  = (int) $m['submission_id'];
						$txn  = $m['txn'];
						$pend = $m['pending'] ?? [];
						$sub_url = admin_url( 'admin.php?page=bmm-submissions&submission_id=' . $sid );
					?>
						<tr>
							<td class="check-column"><input type="checkbox" class="bmm-recon-cb" name="confirm_ids[]" value="<?php echo esc_attr( $sid ); ?>" checked /></td>
							<td>
								<a href="<?php echo esc_url( $sub_url ); ?>"><strong><?php echo esc_html( $pend['name'] ?? ( '#' . $sid ) ); ?></strong></a><br>
								<span class="description"><?php echo esc_html( trim( ( $pend['phone'] ?? '' ) . ' ' . ( $pend['email'] ?? '' ) ) ); ?></span>
								<input type="hidden" name="txn[<?php echo esc_attr( $sid ); ?>][txn_id]"       value="<?php echo esc_attr( $txn['txn_id'] ); ?>" />
								<input type="hidden" name="txn[<?php echo esc_attr( $sid ); ?>][confirmation]" value="<?php echo esc_attr( $txn['confirmation'] ); ?>" />
								<input type="hidden" name="txn[<?php echo esc_attr( $sid ); ?>][last4]"        value="<?php echo esc_attr( $txn['last4'] ); ?>" />
								<input type="hidden" name="txn[<?php echo esc_attr( $sid ); ?>][amount]"       value="<?php echo esc_attr( $txn['amount'] ); ?>" />
							</td>
							<td>₪<?php echo esc_html( (string) $txn['amount'] ); ?></td>
							<td><?php echo esc_html( implode( ' + ', $m['reasons'] ) ); ?></td>
							<td><?php echo esc_html( $txn['txn_id'] ); ?><?php echo $txn['last4'] ? ' · ****' . esc_html( $txn['last4'] ) : ''; ?></td>
							<td><?php echo esc_html( $txn['time'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top:12px;">
					<button type="submit" name="bmm_reconcile_apply" value="1" class="button button-primary"><?php esc_html_e( 'Complete selected', 'bmm-registration' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</div>
