<?php
defined( 'ABSPATH' ) || exit;
// Variables: $entries (array, newest first) from BMM_Callback_Log::all()

$outcome_labels = [
	'completed'            => [ 'Completed', '#065f46', '#d1fae5' ],
	'already_completed'    => [ 'Already completed', '#374151', '#e5e7eb' ],
	'not_approved'         => [ 'Declined / not approved', '#991b1b', '#fee2e2' ],
	'forbidden_ip'         => [ 'REJECTED — wrong IP', '#991b1b', '#fee2e2' ],
	'invalid_token'        => [ 'REJECTED — bad token', '#991b1b', '#fee2e2' ],
	'invalid_payload'      => [ 'Invalid payload', '#92400e', '#fef3c7' ],
	'missing_id_or_token'  => [ 'Missing id/token', '#92400e', '#fef3c7' ],
	'submission_not_found' => [ 'Submission not found', '#92400e', '#fef3c7' ],
	'invalid_form'         => [ 'Invalid form', '#92400e', '#fef3c7' ],
];
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Payment Callbacks', 'bmm-registration' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Every callback Nedarim sent to this site (newest first). Nedarim sends each callback only once with no retry, so a rejected or lost callback can leave a paid submission stuck on "Pending". A row marked REJECTED that has a Transaction ID is a real payment that did not apply — open the submission and mark it Completed.', 'bmm-registration' ); ?>
	</p>

	<?php if ( empty( $entries ) ) : ?>
	<p><?php esc_html_e( 'No callbacks have been received yet.', 'bmm-registration' ); ?></p>
	<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Outcome', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Submission', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Transaction ID', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'bmm-registration' ); ?></th>
				<th><?php esc_html_e( 'From IP', 'bmm-registration' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $entries as $e ) :
			$outcome = (string) ( $e['outcome'] ?? '' );
			$label   = $outcome_labels[ $outcome ] ?? [ $outcome, '#374151', '#e5e7eb' ];
			$sid     = (int) ( $e['submission_id'] ?? 0 );
			$txn     = (string) ( $e['txn'] ?? '' );
			$keva    = (string) ( $e['keva'] ?? '' );
		?>
			<tr>
				<td><?php echo esc_html( (string) ( $e['time'] ?? '' ) ); ?></td>
				<td><span class="bmm-status" style="color:<?php echo esc_attr( $label[1] ); ?>;background:<?php echo esc_attr( $label[2] ); ?>;"><?php echo esc_html( $label[0] ); ?></span></td>
				<td><?php
					if ( $sid ) {
						$url = admin_url( 'admin.php?page=bmm-submissions&submission_id=' . $sid );
						echo '<a href="' . esc_url( $url ) . '">#' . esc_html( (string) $sid ) . '</a>';
					} else {
						echo '—';
					}
				?></td>
				<td><?php echo esc_html( $txn ?: ( $keva ? 'Keva ' . $keva : '—' ) ); ?></td>
				<td><?php echo $e['amount'] !== '' ? '₪' . esc_html( (string) $e['amount'] ) : '—'; ?></td>
				<td><code><?php echo esc_html( (string) ( $e['ip'] ?? '' ) ); ?></code><?php echo empty( $e['ip_ok'] ) ? ' <strong style="color:#b32d2e;">✗</strong>' : ''; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
