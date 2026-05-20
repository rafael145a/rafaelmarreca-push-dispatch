<?php
/**
 * Settings page at Settings → FCM Push Notify.
 *
 * - Upload Firebase service account JSON.
 * - Toggle automatic send on post publish.
 * - Category → FCM topic map.
 * - Default topic (fallback).
 * - Test send button.
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
			'FCM Push Notify',
			'FCM Push Notify',
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$settings = FCM_Push::get_settings();
		$has_cred = '' !== $settings['service_account_path'] && file_exists( $settings['service_account_path'] );

		$notice = '';
		if ( isset( $_GET['mp_msg'] ) ) {
			$msg_key = sanitize_key( wp_unslash( $_GET['mp_msg'] ) );
			$notice  = self::format_notice( $msg_key );
		}

		?>
		<div class="wrap">
			<h1>FCM Push Notify</h1>

			<?php echo $notice; // already escaped in format_notice ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( self::ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />

				<h2>Credentials</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mp_json">Service account JSON</label></th>
						<td>
							<?php if ( $has_cred ) : ?>
								<p>
									<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span>
									Configured — project: <code><?php echo esc_html( $settings['project_id'] ?: '(unknown)' ); ?></code>
								</p>
							<?php else : ?>
								<p style="color:#d63638">
									<span class="dashicons dashicons-warning"></span>
									Not configured. The plugin will not send notifications until this is set.
								</p>
							<?php endif; ?>
							<input type="file" name="mp_json" id="mp_json" accept="application/json,.json" />
							<p class="description">
								Download from Firebase Console → ⚙ Project settings → Service accounts → Generate new private key.
								The file is stored outside the database in <code>wp-content/uploads/fcm-push-private/</code>.
							</p>
						</td>
					</tr>
				</table>

				<h2>Automatic send</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">On post publish</th>
						<td>
							<label>
								<input type="checkbox" name="enabled_auto" value="1"
									<?php checked( ! empty( $settings['enabled_auto'] ) ); ?> />
								Send a push notification automatically when a post is published.
							</label>
							<p class="description">The post's primary category determines the FCM topic (see map below).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_topic">Default topic</label></th>
						<td>
							<input type="text" name="default_topic" id="default_topic"
								value="<?php echo esc_attr( $settings['default_topic'] ); ?>"
								class="regular-text" />
							<p class="description">Used when the post's category is not in the map below.</p>
						</td>
					</tr>
				</table>

				<h2>Category → topic map</h2>
				<p>Map WordPress category IDs to the FCM topic your app subscribes to.</p>
				<table class="form-table" role="presentation">
					<?php foreach ( $settings['category_topics'] as $cat_id => $topic ) : ?>
						<tr>
							<th scope="row">
								<label>Category <?php echo (int) $cat_id; ?></label><br />
								<small><?php
									$cat = get_category( (int) $cat_id );
									echo esc_html( $cat instanceof WP_Term ? $cat->name : '(not found in WordPress)' );
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

				<?php submit_button( 'Save' ); ?>
			</form>

			<hr />
			<h2>Test send</h2>
			<p>Sends a test notification with title "FCM Push Notify — test" to the topic below.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fcm_push_test_nonce', 'mp_test_nonce' ); ?>
				<input type="hidden" name="action" value="fcm_push_test" />
				<input type="text" name="topic" value="<?php echo esc_attr( $settings['default_topic'] ); ?>"
					class="regular-text" />
				<button type="submit" class="button" <?php disabled( ! $has_cred ); ?>>Send test</button>
			</form>
		</div>
		<?php
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( self::ACTION, self::NONCE_NAME );

		$settings = FCM_Push::get_settings();

		if ( ! empty( $_FILES['mp_json']['name'] ) && empty( $_FILES['mp_json']['error'] ) ) {
			$file = $_FILES['mp_json'];

			if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
				self::redirect( 'upload_failed' );
			}
			if ( (int) $file['size'] > 64 * 1024 ) {
				self::redirect( 'file_too_big' );
			}

			$tmp_content = file_get_contents( $file['tmp_name'] );
			$json        = json_decode( (string) $tmp_content, true );
			if ( ! is_array( $json ) || empty( $json['type'] ) || 'service_account' !== $json['type'] ) {
				self::redirect( 'invalid_json' );
			}

			$dir       = FCM_Push::ensure_private_dir();
			$random    = wp_generate_password( 24, false, false );
			$dest_path = trailingslashit( $dir ) . 'sa-' . $random . '.json';

			if ( false === file_put_contents( $dest_path, $tmp_content ) ) {
				self::redirect( 'write_failed' );
			}
			@chmod( $dest_path, 0600 );

			if ( ! empty( $settings['service_account_path'] )
				&& file_exists( $settings['service_account_path'] )
				&& 0 === strpos( realpath( $settings['service_account_path'] ) ?: '', realpath( $dir ) ?: $dir )
			) {
				@unlink( $settings['service_account_path'] );
			}

			$settings['service_account_path'] = $dest_path;
			$settings['project_id']           = (string) ( $json['project_id'] ?? '' );
		}

		$settings['enabled_auto']  = isset( $_POST['enabled_auto'] ) ? 1 : 0;
		$settings['default_topic'] = FCM_Push_Dispatcher::sanitize_topic(
			wp_unslash( $_POST['default_topic'] ?? '' )
		) ?: 'all';

		if ( isset( $_POST['category_topics'] ) && is_array( $_POST['category_topics'] ) ) {
			$map = [];
			foreach ( wp_unslash( $_POST['category_topics'] ) as $cat_id => $topic ) {
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
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'fcm_push_test_nonce', 'mp_test_nonce' );

		$topic = FCM_Push_Dispatcher::sanitize_topic( wp_unslash( $_POST['topic'] ?? '' ) );
		if ( '' === $topic ) {
			self::redirect( 'test_bad_topic' );
		}

		$client = new FCM_Push_Client();
		$result = $client->send_to_topic(
			$topic,
			'FCM Push Notify — test',
			'If you see this, your Firebase setup is working. ✅',
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
			'saved'          => [ 'updated', 'Settings saved.' ],
			'upload_failed'  => [ 'error',   'JSON upload failed.' ],
			'file_too_big'   => [ 'error',   'File too large (max 64 KB).' ],
			'invalid_json'   => [ 'error',   'File is not a valid service account JSON.' ],
			'write_failed'   => [ 'error',   'Could not write file to uploads/.' ],
			'test_ok'        => [ 'updated', 'Test notification sent successfully.' ],
			'test_bad_topic' => [ 'error',   'Invalid topic name.' ],
			'test_failed'    => [ 'error',   'Test failed: ' . esc_html( (string) get_transient( 'fcm_push_last_test_error' ) ) ],
		];
		if ( ! isset( $messages[ $key ] ) ) {
			return '';
		}
		[ $class, $msg ] = $messages[ $key ];
		return '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . $msg . '</p></div>';
	}
}
