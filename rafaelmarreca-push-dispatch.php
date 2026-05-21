<?php
/**
 * Plugin Name:       RafaelMarreca Push Dispatch for Firebase Cloud Messaging
 * Plugin URI:        https://github.com/rafael145a/rafaelmarreca-push-dispatch
 * Description:       Sends push notifications to mobile apps subscribed to topics, using Google's Firebase Cloud Messaging HTTP v1 API. Triggers on post publish or via a dedicated notification composer. Zero external PHP dependencies.
 * Version:           1.0.1
 * Author:            Rafael Marreca
 * Author URI:        https://github.com/rafael145a
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rafaelmarreca-push-dispatch
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 *
 * Uses topic subscriptions — the app subscribes via subscribeToTopic().
 * No device tokens stored, no PII, no custom database tables.
 *
 * Firebase and Firebase Cloud Messaging are trademarks of Google LLC.
 * This plugin is not affiliated with or endorsed by Google.
 */

defined( 'ABSPATH' ) || exit;

define( 'FCM_PUSH_VERSION', '1.0.1' );
define( 'FCM_PUSH_FILE', __FILE__ );
define( 'FCM_PUSH_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCM_PUSH_SLUG', 'rafaelmarreca-push-dispatch' );

define( 'FCM_PUSH_OPTION', 'fcm_push_settings' );
define( 'FCM_PUSH_META_SENT', '_fcm_push_sent_at' );
define( 'FCM_PUSH_CPT', 'fcm_push_notif' );

require_once FCM_PUSH_DIR . 'includes/class-fcm-client.php';
require_once FCM_PUSH_DIR . 'includes/class-settings.php';
require_once FCM_PUSH_DIR . 'includes/class-cpt.php';
require_once FCM_PUSH_DIR . 'includes/class-dispatcher.php';
require_once FCM_PUSH_DIR . 'includes/class-fcm-push.php';

register_activation_hook( __FILE__, [ 'FCM_Push', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'FCM_Push', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'FCM_Push', 'boot' ] );
