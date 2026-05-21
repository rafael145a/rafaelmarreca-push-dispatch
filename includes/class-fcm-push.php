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
	 * Creates wp-content/uploads/rafaelmarreca-push-private/ with .htaccess deny. Idempotent.
	 */
	public static function ensure_private_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'rafaelmarreca-push-private';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;
			$wp_filesystem->put_contents(
				$htaccess,
				"# RafaelMarreca Push Dispatch — blocks public access to service account.\n" .
				"<IfModule mod_authz_core.c>\n" .
				"    Require all denied\n" .
				"</IfModule>\n" .
				"<IfModule !mod_authz_core.c>\n" .
				"    Order deny,allow\n" .
				"    Deny from all\n" .
				"</IfModule>\n",
				FS_CHMOD_FILE
			);
			$wp_filesystem->put_contents( $dir . '/index.html', '', FS_CHMOD_FILE );
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
