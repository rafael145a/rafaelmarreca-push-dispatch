<?php
/**
 * Custom post type "Push Notification" for manual sends.
 * Regular posts are handled separately by FCM_Push_Dispatcher::on_transition().
 */

defined( 'ABSPATH' ) || exit;

class FCM_Push_CPT {

	const META_TOPIC     = '_fcm_push_topic';
	const META_DEEP_LINK = '_fcm_push_deep_link';

	public static function register(): void {
		add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post_' . FCM_PUSH_CPT, [ __CLASS__, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		// Force classic editor — the composer relies on classic editor DOM (#title, #content, etc.).
		add_filter( 'use_block_editor_for_post_type', [ __CLASS__, 'disable_block_editor' ], 10, 2 );
	}

	public static function disable_block_editor( bool $use, string $post_type ): bool {
		return $post_type === FCM_PUSH_CPT ? false : $use;
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== FCM_PUSH_CPT ) {
			return;
		}
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$base = plugin_dir_url( FCM_PUSH_FILE ) . 'assets/';
		$ver  = FCM_PUSH_VERSION;

		wp_enqueue_style( 'fcm-push-admin', $base . 'admin.css', [], $ver );
		wp_enqueue_script( 'fcm-push-admin', $base . 'admin.js', [ 'wp-dom-ready' ], $ver, true );
		wp_localize_script( 'fcm-push-admin', 'fcmPushData', [
			'siteName' => get_bloginfo( 'name' ),
		] );
	}

	public static function register_cpt(): void {
		register_post_type(
			FCM_PUSH_CPT,
			[
				'label'           => __( 'Notifications', 'rafaelmarreca-fcm-push-notify' ),
				'labels'          => [
					'name'               => _x( 'Notifications', 'post type general name', 'rafaelmarreca-fcm-push-notify' ),
					'singular_name'      => _x( 'Notification', 'post type singular name', 'rafaelmarreca-fcm-push-notify' ),
					'add_new_item'       => __( 'New Notification', 'rafaelmarreca-fcm-push-notify' ),
					'edit_item'          => __( 'Edit Notification', 'rafaelmarreca-fcm-push-notify' ),
					'menu_name'          => _x( 'Notifications', 'admin menu', 'rafaelmarreca-fcm-push-notify' ),
					'add_new'            => __( 'New Notification', 'rafaelmarreca-fcm-push-notify' ),
					'view_item'          => __( 'View Notification', 'rafaelmarreca-fcm-push-notify' ),
					'search_items'       => __( 'Search Notifications', 'rafaelmarreca-fcm-push-notify' ),
					'not_found'          => __( 'No notifications found.', 'rafaelmarreca-fcm-push-notify' ),
					'not_found_in_trash' => __( 'No notifications found in Trash.', 'rafaelmarreca-fcm-push-notify' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-bell',
				'menu_position'   => 30,
				'supports'        => [ 'title', 'editor' ],
				'capability_type' => 'post',
			]
		);
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'fcm-push-target',
			__( 'Send to', 'rafaelmarreca-fcm-push-notify' ),
			[ __CLASS__, 'render_meta_box' ],
			FCM_PUSH_CPT,
			'side',
			'default'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'fcm_push_meta', 'fcm_push_meta_nonce' );

		$settings = FCM_Push::get_settings();
		$topics   = array_unique( array_filter( array_merge(
			[ $settings['default_topic'] ],
			array_values( $settings['category_topics'] )
		) ) );
		sort( $topics );

		$current_topic = get_post_meta( $post->ID, self::META_TOPIC, true );
		if ( '' === $current_topic ) {
			$current_topic = $settings['default_topic'];
		}
		$deep_link = (string) get_post_meta( $post->ID, self::META_DEEP_LINK, true );
		$sent_at   = (string) get_post_meta( $post->ID, FCM_PUSH_META_SENT, true );

		echo '<div class="mp-fields-group">';

		echo '<div class="mp-field">';
		echo '<label class="mp-field-label" for="fcm_push_topic">' . esc_html__( 'FCM topic', 'rafaelmarreca-fcm-push-notify' ) . '</label>';
		echo '<select name="fcm_push_topic" id="fcm_push_topic" class="mp-topic-select">';
		foreach ( $topics as $t ) {
			$t = (string) $t;
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $t ),
				selected( $current_topic, $t, false )
			);
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="mp-field">';
		echo '<label class="mp-field-label" for="fcm_push_deep_link">';
		echo esc_html__( 'Deep link', 'rafaelmarreca-fcm-push-notify' );
		echo ' <span style="font-weight:400;text-transform:none">(' . esc_html__( 'optional', 'rafaelmarreca-fcm-push-notify' ) . ')</span>';
		echo '</label>';
		echo '<input type="text" name="fcm_push_deep_link" id="fcm_push_deep_link" ';
		echo 'class="mp-deeplink-input" value="' . esc_attr( $deep_link ) . '" ';
		echo 'placeholder="myapp://section/123" />';
		echo '</div>';

		if ( '' !== $sent_at ) {
			echo '<div class="mp-sent-status"><span class="dashicons dashicons-yes-alt"></span> ';
			printf(
				/* translators: %s: date and time the notification was sent */
				esc_html__( 'Sent at %s', 'rafaelmarreca-fcm-push-notify' ),
				esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sent_at ) )
			);
			echo '</div>';
		} else {
			echo '<div class="mp-pending-status">' . esc_html__( 'Will be sent when published.', 'rafaelmarreca-fcm-push-notify' ) . '</div>';
		}

		echo '</div>';
	}

	public static function save_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['fcm_push_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcm_push_meta_nonce'] ) ), 'fcm_push_meta' )
		) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$topic = isset( $_POST['fcm_push_topic'] )
			? FCM_Push_Dispatcher::sanitize_topic( sanitize_text_field( wp_unslash( $_POST['fcm_push_topic'] ) ) )
			: '';
		if ( '' !== $topic ) {
			update_post_meta( $post_id, self::META_TOPIC, $topic );
		}

		$deep = isset( $_POST['fcm_push_deep_link'] )
			? sanitize_text_field( wp_unslash( $_POST['fcm_push_deep_link'] ) )
			: '';
		// esc_url_raw rejects custom URL schemes; validate manually (RFC 3986 scheme).
		if ( '' !== $deep && ! preg_match( '#^[a-zA-Z][a-zA-Z0-9+\-.]*://[a-zA-Z0-9/_\-.~%]+$#', $deep ) ) {
			$deep = '';
		}
		if ( '' !== $deep ) {
			update_post_meta( $post_id, self::META_DEEP_LINK, $deep );
		} else {
			delete_post_meta( $post_id, self::META_DEEP_LINK );
		}
	}
}
