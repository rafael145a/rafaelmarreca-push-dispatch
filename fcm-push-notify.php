<?php
/**
 * Plugin Name: FCM Push Notify
 * Description: Send Firebase Cloud Messaging (FCM HTTP v1) push notifications to mobile apps when posts are published, or manually via a dedicated notification composer. Zero external dependencies — uses WordPress HTTP API + OpenSSL only.
 * Version:     1.0.0
 * Author:      Rafael Marreca
 * License:     GPL-2.0-or-later
 * Text Domain: fcm-push-notify
 * Requires PHP: 7.4
 *
 * Uses FCM topic subscriptions — the app subscribes via subscribeToTopic().
 * No device tokens stored, no PII, no custom database tables.
 *
 * Service account JSON: stored outside the database in
 * wp-content/uploads/fcm-push-private/ (.htaccess deny + chmod 600).
 */

defined( 'ABSPATH' ) || exit;

define( 'FCM_PUSH_VERSION', '1.0.0' );
define( 'FCM_PUSH_FILE', __FILE__ );
define( 'FCM_PUSH_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCM_PUSH_SLUG', 'fcm-push-notify' );

// WordPress option key for serialised settings.
define( 'FCM_PUSH_OPTION', 'fcm_push_settings' );

// Post meta key that marks a push as already sent (idempotency).
define( 'FCM_PUSH_META_SENT', '_fcm_push_sent_at' );

// Custom post type slug.
define( 'FCM_PUSH_CPT', 'fcm_push_notif' );

require_once FCM_PUSH_DIR . 'includes/class-fcm-client.php';
require_once FCM_PUSH_DIR . 'includes/class-settings.php';
require_once FCM_PUSH_DIR . 'includes/class-cpt.php';
require_once FCM_PUSH_DIR . 'includes/class-dispatcher.php';
require_once FCM_PUSH_DIR . 'includes/class-fcm-push.php';

register_activation_hook( __FILE__, [ 'FCM_Push', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'FCM_Push', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'FCM_Push', 'boot' ] );
