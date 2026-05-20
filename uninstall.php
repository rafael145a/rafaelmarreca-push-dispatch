<?php
/**
 * Uninstall: removes settings and the private credentials directory.
 * Posts/notifications are left intact (they are legitimate content).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$opt = get_option( 'fcm_push_settings' );
if ( is_array( $opt ) && ! empty( $opt['service_account_path'] ) && file_exists( $opt['service_account_path'] ) ) {
	@unlink( $opt['service_account_path'] );
}

$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'fcm-push-private';
if ( is_dir( $dir ) ) {
	foreach ( (array) glob( $dir . '/*' ) as $f ) {
		@unlink( $f );
	}
	@rmdir( $dir );
}

delete_option( 'fcm_push_settings' );
delete_transient( 'fcm_push_access_token' );
delete_transient( 'fcm_push_last_test_error' );
