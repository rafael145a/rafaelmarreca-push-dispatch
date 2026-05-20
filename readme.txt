=== FCM Push Notify ===
Contributors: rafael145a
Tags: fcm, firebase, push notifications, mobile, cloud messaging
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Firebase Cloud Messaging push notifications to mobile apps automatically on post publish or via a dedicated notification composer.

== Disclaimer ==

Firebase and Firebase Cloud Messaging (FCM) are trademarks of Google LLC. This plugin is not affiliated with or endorsed by Google.

== Description ==

**FCM Push Notify** connects your WordPress site to your mobile app via Firebase Cloud Messaging (FCM HTTP v1). No SDK, no Guzzle, no external dependencies — just WordPress HTTP API and OpenSSL.

= How it works =

* Uses **FCM topic subscriptions** — your app calls `subscribeToTopic()` for each topic it wants to receive. No device tokens stored, no PII, no extra database tables.
* When a post is published, the **primary category** determines the FCM topic (configurable via Settings → FCM Push Notify).
* A dedicated **notification composer** (custom post type) lets you send manual push notifications with a custom title, body, topic, and optional deep link — without creating a real post.

= Features =

* FCM HTTP v1 API with OAuth2 JWT authentication (RS256)
* Automatic push on post publish, mapped by category
* Manual notification composer with live phone preview and character counters
* Deep link support (any custom URL scheme)
* OAuth2 access token cached for 55 minutes to minimise round-trips
* Service account JSON stored outside the database with `.htaccess` deny + chmod 600
* Idempotent dispatch — republishing never resends

= Requirements =

* A Firebase project with FCM enabled
* A Firebase service account JSON (downloaded from Firebase Console)
* PHP `openssl` extension (bundled with most hosts)
* Your mobile app must call `subscribeToTopic()` for each topic

== Installation ==

1. Upload the `fcm-push-notify` folder to `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Go to **Settings → FCM Push Notify** and upload your Firebase service account JSON.
4. Set up the category → topic map (optional — uses the default topic if a category is not mapped).
5. Click **Send test** to verify your credentials before going live.

== Service Account Setup ==

In the Firebase Console: ⚙ **Project settings** → **Service accounts** → **Generate new private key** → download the JSON → upload it in the plugin settings.

The file is stored in `wp-content/uploads/fcm-push-notify-private/` with `.htaccess` deny rules and chmod 600. It is never stored in the database.

== Mobile App Setup ==

Your app needs to call `subscribeToTopic(getMessaging(), '<topic-name>')` to receive notifications for a given topic. FCM v1 delivers to zero devices if no device is subscribed.

Example (React Native / Firebase JS SDK):
`await messaging().subscribeToTopic('all');`

== Frequently Asked Questions ==

= Does this store device tokens? =

No. The plugin uses FCM topic subscriptions exclusively. Your app subscribes on the device; the plugin only sends to topic names.

= What is the character limit for notifications? =

The plugin enforces 65 characters for the title and 140 characters for the body. The notification composer shows a live character counter and a phone preview as you type.

= Can I send a notification without publishing a post? =

Yes. Use the **Notifications** menu item to open the notification composer, write your title and body, choose a topic, and publish.

= How do I set up deep links? =

Enter a custom URL in the **Deep link** field of the notification composer, e.g. `myapp://section/123`. Any RFC 3986 compliant custom scheme is accepted.

== Changelog ==

= 1.0.0 =
* Initial release.
