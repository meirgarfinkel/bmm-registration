<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php esc_html_e( 'Submissions', 'bmm-registration' ); ?></h1>
	<form method="get">
		<input type="hidden" name="page" value="bmm-submissions" />
		<?php $list_table->display(); ?>
	</form>
</div>
