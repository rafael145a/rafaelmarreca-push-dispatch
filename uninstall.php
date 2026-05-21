<?php
/**
 * Uninstall: removes settings and the private credentials directory.
 * Posts/notifications are left intact (they are legitimate content).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$fcm_push_opt = get_option( 'fcm_push_settings' );
if ( is_array( $fcm_push_opt ) && ! empty( $fcm_push_opt['service_account_path'] ) && file_exists( $fcm_push_opt['service_account_path'] ) ) {
	wp_delete_file( $fcm_push_opt['service_account_path'] );
}

$fcm_push_uploads = wp_upload_dir();
$fcm_push_dir     = trailingslashit( $fcm_push_uploads['basedir'] ) . 'rafaelmarreca-push-private';

if ( is_dir( $fcm_push_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;

	foreach ( (array) glob( $fcm_push_dir . '/*' ) as $fcm_push_file ) {
		$wp_filesystem->delete( $fcm_push_file );
	}
	$wp_filesystem->rmdir( $fcm_push_dir );
}

delete_option( 'fcm_push_settings' );
delete_transient( 'fcm_push_access_token' );
delete_transient( 'fcm_push_last_test_error' );
