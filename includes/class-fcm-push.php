<?php
/**
 * Bootstrap — registers hooks for all modules.
 */

defined( 'ABSPATH' ) || exit;

class FCM_Push {

	public static function boot(): void {
		FCM_Push_Settings::register();
		FCM_Push_CPT::register();
		FCM_Push_Dispatcher::register();
	}

	public static function activate(): void {
		self::ensure_private_dir();

		if ( false === get_option( FCM_PUSH_OPTION ) ) {
			add_option( FCM_PUSH_OPTION, self::default_settings() );
		}

		FCM_Push_CPT::register_cpt();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Creates wp-content/uploads/fcm-push-private/ with .htaccess deny. Idempotent.
	 */
	public static function ensure_private_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'fcm-push-private';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents(
				$htaccess,
				"# FCM Push Notify — blocks public access to service account.\n" .
				"<IfModule mod_authz_core.c>\n" .
				"    Require all denied\n" .
				"</IfModule>\n" .
				"<IfModule !mod_authz_core.c>\n" .
				"    Order deny,allow\n" .
				"    Deny from all\n" .
				"</IfModule>\n"
			);
		}

		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' );
		}

		return $dir;
	}

	public static function default_settings(): array {
		return [
			'service_account_path' => '',
			'project_id'           => '',
			'enabled_auto'         => 1,
			'default_topic'        => 'all',
			'category_topics'      => [],
		];
	}

	public static function get_settings(): array {
		$saved = get_option( FCM_PUSH_OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return wp_parse_args( $saved, self::default_settings() );
	}
}
