=== RafaelMarreca Push Dispatch for Firebase Cloud Messaging ===
Contributors: rafael145a
Tags: push notifications, mobile, firebase, cloud messaging, topic
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send push notifications to mobile apps via Google's Firebase Cloud Messaging — on post publish or via a notification composer.

== Disclaimer ==

Firebase, Firebase Cloud Messaging and Google are trademarks of Google LLC. This plugin is an independent, third-party integration and is not affiliated with, endorsed by, or sponsored by Google LLC.

== Description ==

**RafaelMarreca Push Dispatch** connects your WordPress site to your mobile app by sending notifications through Google's Firebase Cloud Messaging (FCM HTTP v1) service. No PHP SDK, no Guzzle, no external PHP dependencies — just the WordPress HTTP API and the OpenSSL extension.

= How it works =

* Uses **topic subscriptions** — your mobile app calls `subscribeToTopic()` for each topic it wants to receive. No device tokens are stored, no PII is collected, no extra database tables are created.
* When a post is published, the **primary category** determines the topic name (configurable via Settings → RafaelMarreca Push Dispatch).
* A dedicated **notification composer** (custom post type) lets you send manual push notifications with a custom title, body, topic, and optional deep link — without creating a real post.

= Features =

* OAuth2 JWT authentication (RS256) for FCM HTTP v1
* Automatic push on post publish, mapped by category
* Manual notification composer with live phone preview and character counters
* Deep link support (any custom URL scheme)
* OAuth2 access token cached for 55 minutes to minimise round-trips
* Service account JSON stored outside the database with `.htaccess` deny + chmod 600
* Idempotent dispatch — republishing never resends

= Requirements =

* A Firebase project with Cloud Messaging enabled
* A Firebase service account JSON (downloaded from the Firebase Console)
* PHP `openssl` extension (bundled with most hosts)
* Your mobile app must call `subscribeToTopic()` for each topic

== Installation ==

1. Upload the `rafaelmarreca-push-dispatch` folder to `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Go to **Settings → RafaelMarreca Push Dispatch** and upload your Firebase service account JSON.
4. Set up the category → topic map (optional — uses the default topic if a category is not mapped).
5. Click **Send test** to verify your credentials before going live.

== Service Account Setup ==

In the Firebase Console: ⚙ **Project settings** → **Service accounts** → **Generate new private key** → download the JSON → upload it in the plugin settings.

The file is stored in `wp-content/uploads/rafaelmarreca-push-private/` with `.htaccess` deny rules and chmod 600. It is never stored in the database.

== Mobile App Setup ==

Your app needs to call `subscribeToTopic(getMessaging(), '<topic-name>')` to receive notifications for a given topic. The Firebase Cloud Messaging v1 API delivers to zero devices if no device is subscribed.

Example (React Native / Firebase JS SDK):
`await messaging().subscribeToTopic('all');`

== External services ==

This plugin relies on two external services operated by Google LLC in order to deliver push notifications. Both are part of the **Google Cloud / Firebase** platform. The plugin will not send any data to them until you configure it with a Firebase service account JSON and either publish a post (with auto-send enabled), publish a notification via the composer, or click "Send test" on the settings page.

= 1. Google OAuth2 (oauth2.googleapis.com) =

* **What it is and what it is used for:** Google's OAuth2 token service. The plugin needs a short-lived access token to authenticate against Firebase Cloud Messaging. To obtain it, the plugin signs a JWT locally with the private key from your service account JSON and exchanges it for an access token.
* **When data is sent:** Once when the cached access token expires (every 55 minutes) and on the first send action.
* **What data is sent:** A POST request to `https://oauth2.googleapis.com/token` containing the OAuth2 `urn:ietf:params:oauth:grant-type:jwt-bearer` grant and a signed JWT. The JWT contains your service account's `client_email`, the scope `https://www.googleapis.com/auth/firebase.messaging`, the audience URL, and issued-at/expiration timestamps. **No WordPress user data, no post content, and no visitor data is sent.**
* **Provider:** Google LLC
* **Terms of service:** https://policies.google.com/terms
* **Privacy policy:** https://policies.google.com/privacy

= 2. Firebase Cloud Messaging (fcm.googleapis.com) =

* **What it is and what it is used for:** Google's push notification delivery service. The plugin uses the FCM HTTP v1 API to publish a notification to a topic that your mobile app has subscribed to.
* **When data is sent:** Every time a notification is dispatched — that is, when a post is published (with the auto-send option enabled), when a notification is published via the "Notifications" composer, or when the administrator clicks "Send test" on the settings page.
* **What data is sent:** A POST request to `https://fcm.googleapis.com/v1/projects/{your_project_id}/messages:send` containing: the destination topic name (e.g. "news"), the notification title (max 65 chars) and body (max 140 chars) you typed or that were extracted from your post, an optional deep link URL (when set in the composer), and the post ID / slug / category ID as plain identifiers in the data payload. **No WordPress user data, no IP addresses, and no device identifiers are sent.**
* **Provider:** Google LLC
* **Terms of service:** https://policies.google.com/terms
* **Firebase-specific terms:** https://firebase.google.com/terms
* **Privacy policy:** https://policies.google.com/privacy

By configuring the plugin with your Firebase service account JSON and publishing posts or notifications, you agree to the Google Terms of Service, the Firebase Terms of Service, and the Google Privacy Policy linked above.

== Frequently Asked Questions ==

= Does this store device tokens? =

No. The plugin uses topic subscriptions exclusively. Your app subscribes on the device; the plugin only sends to topic names.

= What is the character limit for notifications? =

The plugin enforces 65 characters for the title and 140 characters for the body. The notification composer shows a live character counter and a phone preview as you type.

= Can I send a notification without publishing a post? =

Yes. Use the **Notifications** menu item to open the notification composer, write your title and body, choose a topic, and publish.

= How do I set up deep links? =

Enter a custom URL in the **Deep link** field of the notification composer, e.g. `myapp://section/123`. Any RFC 3986 compliant custom scheme is accepted.

= Is this plugin affiliated with Firebase or Google? =

No. This is an independent, third-party integration that uses Google's publicly documented Firebase Cloud Messaging HTTP v1 API. "Firebase" and "Firebase Cloud Messaging" are trademarks of Google LLC.

== Changelog ==

= 1.0.1 =
* Renamed plugin to "RafaelMarreca Push Dispatch for Firebase Cloud Messaging" (new slug: rafaelmarreca-push-dispatch) to comply with WordPress.org trademark guidelines.
* Added an "External services" section to the readme documenting the use of Google OAuth2 and Firebase Cloud Messaging, with links to Google's Terms of Service and Privacy Policy.
* Removed deprecated `openssl_free_key()` call (PHP 8+ no longer requires it; PHP 7.4 garbage-collects the key at scope exit).

= 1.0.0 =
* Initial release.
