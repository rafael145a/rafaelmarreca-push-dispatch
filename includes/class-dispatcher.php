<?php
/**
 * Dispatcher — decides when and what to send.
 *
 * Two paths:
 *  1. Regular post published → topic from primary category.
 *  2. CPT "Notification" published → topic chosen in the meta box.
 */

defined( 'ABSPATH' ) || exit;

class FCM_Push_Dispatcher {

	/** Valid FCM topic regex (https://firebase.google.com/docs/cloud-messaging/concept-options#topic-format). */
	const TOPIC_REGEX = '/^[a-zA-Z0-9\-_.~%]+$/';

	public static function register(): void {
		add_action( 'transition_post_status', [ __CLASS__, 'on_transition' ], 10, 3 );
	}

	public static function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status ) {
			return;
		}
		if ( 'publish' === $old_status ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, [ 'post', FCM_PUSH_CPT ], true ) ) {
			return;
		}

		if ( '' !== (string) get_post_meta( $post->ID, FCM_PUSH_META_SENT, true ) ) {
			return;
		}

		if ( FCM_PUSH_CPT === $post->post_type ) {
			self::dispatch_notification( $post );
			return;
		}

		$settings = FCM_Push::get_settings();
		if ( empty( $settings['enabled_auto'] ) ) {
			return;
		}
		self::dispatch_post( $post, $settings );
	}

	private static function dispatch_post( WP_Post $post, array $settings ): void {
		$cat_ids = wp_get_post_categories( $post->ID );
		if ( empty( $cat_ids ) ) {
			return;
		}

		sort( $cat_ids );
		$primary = (int) $cat_ids[0];

		$map   = (array) $settings['category_topics'];
		$topic = $map[ $primary ] ?? $settings['default_topic'];
		$topic = self::sanitize_topic( $topic );
		if ( '' === $topic ) {
			return;
		}

		$title = self::truncate( wp_strip_all_tags( $post->post_title ), 65 );
		$body  = self::truncate( wp_strip_all_tags( get_the_excerpt( $post ) ), 140 );
		if ( '' === $body ) {
			$body = self::truncate( wp_strip_all_tags( $post->post_content ), 140 );
		}
		if ( '' === $title ) {
			return;
		}

		$client = new FCM_Push_Client();
		$result = $client->send_to_topic(
			$topic,
			$title,
			$body,
			[
				'type'        => 'post',
				'post_id'     => (string) $post->ID,
				'slug'        => $post->post_name,
				'category_id' => (string) $primary,
			]
		);

		self::mark_sent( $post->ID, $topic, $result );
	}

	private static function dispatch_notification( WP_Post $post ): void {
		$topic = (string) get_post_meta( $post->ID, FCM_Push_CPT::META_TOPIC, true );
		if ( '' === $topic ) {
			$settings = FCM_Push::get_settings();
			$topic    = (string) $settings['default_topic'];
		}
		$topic = self::sanitize_topic( $topic );
		if ( '' === $topic ) {
			return;
		}

		$title = self::truncate( wp_strip_all_tags( $post->post_title ), 65 );
		$body  = self::truncate( wp_strip_all_tags( $post->post_content ), 140 );
		if ( '' === $title || '' === $body ) {
			return;
		}

		$deep = (string) get_post_meta( $post->ID, FCM_Push_CPT::META_DEEP_LINK, true );

		$data = [
			'type'    => 'notification',
			'post_id' => (string) $post->ID,
		];
		if ( '' !== $deep ) {
			$data['deep_link'] = $deep;
		}

		$client = new FCM_Push_Client();
		$result = $client->send_to_topic( $topic, $title, $body, $data );

		self::mark_sent( $post->ID, $topic, $result );
	}

	private static function mark_sent( int $post_id, string $topic, array $result ): void {
		if ( ! empty( $result['ok'] ) ) {
			update_post_meta( $post_id, FCM_PUSH_META_SENT, current_time( 'mysql' ) );
		}
		update_post_meta(
			$post_id,
			'_fcm_push_last_result',
			[
				'topic' => $topic,
				'ok'    => ! empty( $result['ok'] ),
				'http'  => (int) ( $result['http'] ?? 0 ),
				'error' => (string) ( $result['error'] ?? '' ),
				'at'    => current_time( 'mysql' ),
			]
		);
	}

	public static function sanitize_topic( $raw ): string {
		$t = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $t ) {
			return '';
		}
		if ( ! preg_match( self::TOPIC_REGEX, $t ) ) {
			return '';
		}
		return $t;
	}

	private static function truncate( string $s, int $max ): string {
		$s = trim( $s );
		if ( '' === $s ) {
			return '';
		}
		if ( mb_strlen( $s ) <= $max ) {
			return $s;
		}
		return rtrim( mb_substr( $s, 0, $max - 1 ) ) . '…';
	}
}
