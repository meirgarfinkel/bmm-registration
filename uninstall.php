<?php
// Runs when the plugin is deleted from WP admin.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options
delete_option( 'bmm_registration_settings' );

// Remove all form posts and their meta
$form_posts = get_posts( [
	'post_type'      => 'bmm_reg_form',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );
foreach ( $form_posts as $id ) {
	wp_delete_post( $id, true );
}

// Remove all submission posts and their meta
$submission_posts = get_posts( [
	'post_type'      => 'bmm_submission',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );
foreach ( $submission_posts as $id ) {
	wp_delete_post( $id, true );
}
