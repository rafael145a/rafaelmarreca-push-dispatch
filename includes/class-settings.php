<?php
/**
 * Settings page at Settings → FCM Push Notify.
 */

defined( 'ABSPATH' ) || exit;

class FCM_Push_Settings {

	const PAGE_SLUG  = 'fcm-push-notify';
	const NONCE_NAME = 'fcm_push_settings_nonce';
	const ACTION     = 'fcm_push_save_settings';

	public static function register(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_fcm_push_test', [ __CLASS__, 'handle_test_send' ] );
	}

	public static function add_menu(): void {
		add_options_page(
			__( 'FCM Push Notify', 'fcm-push-notify' ),
			__( 'FCM Push Notify', 'fcm-push-notify' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fcm-push-notify' ) );
		}

		$settings = FCM_Push::get_settings();
		$has_cred = '' !== $settings['service_account_path'] && file_exists( $settings['service_account_path'] );

		$notice = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, value set by our own safe redirect
		if ( isset( $_GET['mp_msg'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg_key = sanitize_key( wp_unslash( $_GET['mp_msg'] ) );
			$notice  = self::format_notice( $msg_key );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FCM Push Notify', 'fcm-push-notify' ); ?></h1>

			<?php echo wp_kses_post( $notice ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( self::ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />

				<h2><?php esc_html_e( 'Credentials', 'fcm-push-notify' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mp_json"><?php esc_html_e( 'Service account JSON', 'fcm-push-notify' ); ?></label></th>
						<td>
							<?php if ( $has_cred ) : ?>
								<p>
									<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span>
									<?php
									printf(
										/* translators: %s: Firebase project ID */
										esc_html__( 'Configured — project: %s', 'fcm-push-notify' ),
										'<code>' . esc_html( $settings['project_id'] ?: __( '(unknown)', 'fcm-push-notify' ) ) . '</code>'
									);
									?>
								</p>
							<?php else : ?>
								<p style="color:#d63638">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Not configured. The plugin will not send notifications until this is set.', 'fcm-push-notify' ); ?>
								</p>
							<?php endif; ?>
							<input type="file" name="mp_json" id="mp_json" accept="application/json,.json" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: directory path */
									esc_html__( 'Download from Firebase Console → ⚙ Project settings → Service accounts → Generate new private key. The file is stored outside the database in %s.', 'fcm-push-notify' ),
									'<code>wp-content/uploads/fcm-push-private/</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Automatic send', 'fcm-push-notify' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'On post publish', 'fcm-push-notify' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled_auto" value="1"
									<?php checked( ! empty( $settings['enabled_auto'] ) ); ?> />
								<?php esc_html_e( 'Send a push notification automatically when a post is published.', 'fcm-push-notify' ); ?>
							</label>
							<p class="description"><?php esc_html_e( "The post's primary category determines the FCM topic (see map below).", 'fcm-push-notify' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_topic"><?php esc_html_e( 'Default topic', 'fcm-push-notify' ); ?></label></th>
						<td>
							<input type="text" name="default_topic" id="default_topic"
								value="<?php echo esc_attr( $settings['default_topic'] ); ?>"
								class="regular-text" />
							<p class="description"><?php esc_html_e( "Used when the post's category is not in the map below.", 'fcm-push-notify' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Category → topic map', 'fcm-push-notify' ); ?></h2>
				<p><?php esc_html_e( 'Map WordPress category IDs to the FCM topic your app subscribes to.', 'fcm-push-notify' ); ?></p>
				<table class="form-table" role="presentation">
					<?php foreach ( $settings['category_topics'] as $cat_id => $topic ) : ?>
						<tr>
							<th scope="row">
								<label>
									<?php
									printf(
										/* translators: %d: category ID */
										esc_html__( 'Category %d', 'fcm-push-notify' ),
										(int) $cat_id
									);
									?>
								</label><br />
								<small><?php
									$cat = get_category( (int) $cat_id );
									echo esc_html( $cat instanceof WP_Term ? $cat->name : __( '(not found in WordPress)', 'fcm-push-notify' ) );
								?></small>
							</th>
							<td>
								<input type="text"
									name="category_topics[<?php echo (int) $cat_id; ?>]"
									value="<?php echo esc_attr( $topic ); ?>"
									class="regular-text" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button( __( 'Save', 'fcm-push-notify' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Test send', 'fcm-push-notify' ); ?></h2>
			<p><?php esc_html_e( 'Sends a test notification with title "FCM Push Notify — test" to the topic below.', 'fcm-push-notify' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fcm_push_test_nonce', 'mp_test_nonce' ); ?>
				<input type="hidden" name="action" value="fcm_push_test" />
				<input type="text" name="topic" value="<?php echo esc_attr( $settings['default_topic'] ); ?>"
					class="regular-text" />
				<button type="submit" class="button" <?php disabled( ! $has_cred ); ?>><?php esc_html_e( 'Send test', 'fcm-push-notify' ); ?></button>
			</form>
		</div>
		<?php
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fcm-push-notify' ) );
		}
		check_admin_referer( self::ACTION, self::NONCE_NAME );

		$settings = FCM_Push::get_settings();

		$file_name  = isset( $_FILES['mp_json']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['mp_json']['name'] ) ) : '';
		$file_error = isset( $_FILES['mp_json']['error'] ) ? (int) $_FILES['mp_json']['error'] : UPLOAD_ERR_NO_FILE;
		$file_size  = isset( $_FILES['mp_json']['size'] ) ? (int) $_FILES['mp_json']['size'] : 0;
		$file_tmp   = isset( $_FILES['mp_json']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['mp_json']['tmp_name'] ) ) : '';

		if ( '' !== $file_name && UPLOAD_ERR_NO_FILE !== $file_error ) {
			if ( UPLOAD_ERR_OK !== $file_error ) {
				self::redirect( 'upload_failed' );
			}
			if ( $file_size > 64 * 1024 ) {
				self::redirect( 'file_too_big' );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$tmp_content = file_get_contents( $file_tmp );
			$json        = json_decode( (string) $tmp_content, true );
			if ( ! is_array( $json ) || empty( $json['type'] ) || 'service_account' !== $json['type'] ) {
				self::redirect( 'invalid_json' );
			}

			$dir       = FCM_Push::ensure_private_dir();
			$random    = wp_generate_password( 24, false, false );
			$dest_path = trailingslashit( $dir ) . 'sa-' . $random . '.json';

			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;

			if ( false === $wp_filesystem->put_contents( $dest_path, $tmp_content, FS_CHMOD_FILE ) ) {
				self::redirect( 'write_failed' );
			}
			$wp_filesystem->chmod( $dest_path, 0600 );

			if ( ! empty( $settings['service_account_path'] )
				&& file_exists( $settings['service_account_path'] )
				&& 0 === strpos( realpath( $settings['service_account_path'] ) ?: '', realpath( $dir ) ?: $dir )
			) {
				wp_delete_file( $settings['service_account_path'] );
			}

			$settings['service_account_path'] = $dest_path;
			$settings['project_id']           = (string) ( $json['project_id'] ?? '' );
		}

		$settings['enabled_auto']  = isset( $_POST['enabled_auto'] ) ? 1 : 0;
		$settings['default_topic'] = FCM_Push_Dispatcher::sanitize_topic(
			sanitize_text_field( wp_unslash( $_POST['default_topic'] ?? '' ) )
		) ?: 'all';

		if ( isset( $_POST['category_topics'] ) && is_array( $_POST['category_topics'] ) ) {
			$map = [];
			foreach ( array_map( 'sanitize_text_field', wp_unslash( $_POST['category_topics'] ) ) as $cat_id => $topic ) {
				$cat_id = (int) $cat_id;
				$topic  = FCM_Push_Dispatcher::sanitize_topic( $topic );
				if ( $cat_id > 0 && '' !== $topic ) {
					$map[ $cat_id ] = $topic;
				}
			}
			$settings['category_topics'] = $map;
		}

		update_option( FCM_PUSH_OPTION, $settings );
		self::redirect( 'saved' );
	}

	public static function handle_test_send(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fcm-push-notify' ) );
		}
		check_admin_referer( 'fcm_push_test_nonce', 'mp_test_nonce' );

		$topic = FCM_Push_Dispatcher::sanitize_topic(
			sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) )
		);
		if ( '' === $topic ) {
			self::redirect( 'test_bad_topic' );
		}

		$client = new FCM_Push_Client();
		$result = $client->send_to_topic(
			$topic,
			__( 'FCM Push Notify — test', 'fcm-push-notify' ),
			__( 'If you see this, your Firebase setup is working. ✅', 'fcm-push-notify' ),
			[ 'type' => 'test', 'sent_at' => current_time( 'mysql' ) ]
		);

		if ( ! empty( $result['ok'] ) ) {
			self::redirect( 'test_ok' );
		}

		$err = $result['error'] ?? ( 'HTTP ' . (int) ( $result['http'] ?? 0 ) );
		set_transient( 'fcm_push_last_test_error', $err, 60 );
		self::redirect( 'test_failed' );
	}

	private static function redirect( string $msg ): void {
		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG, 'mp_msg' => $msg ],
			admin_url( 'options-general.php' )
		) );
		exit;
	}

	private static function format_notice( string $key ): string {
		$messages = [
			'saved'          => [ 'updated', __( 'Settings saved.', 'fcm-push-notify' ) ],
			'upload_failed'  => [ 'error',   __( 'JSON upload failed.', 'fcm-push-notify' ) ],
			'file_too_big'   => [ 'error',   __( 'File too large (max 64 KB).', 'fcm-push-notify' ) ],
			'invalid_json'   => [ 'error',   __( 'File is not a valid service account JSON.', 'fcm-push-notify' ) ],
			'write_failed'   => [ 'error',   __( 'Could not write file to uploads/.', 'fcm-push-notify' ) ],
			'test_ok'        => [ 'updated', __( 'Test notification sent successfully.', 'fcm-push-notify' ) ],
			'test_bad_topic' => [ 'error',   __( 'Invalid topic name.', 'fcm-push-notify' ) ],
			'test_failed'    => [
				'error',
				sprintf(
					/* translators: %s: error message from FCM */
					__( 'Test failed: %s', 'fcm-push-notify' ),
					esc_html( (string) get_transient( 'fcm_push_last_test_error' ) )
				),
			],
		];
		if ( ! isset( $messages[ $key ] ) ) {
			return '';
		}
		[ $class, $msg ] = $messages[ $key ];
		return '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses_post( $msg ) . '</p></div>';
	}
}
