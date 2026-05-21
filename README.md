# RafaelMarreca Push Dispatch for Firebase Cloud Messaging

> Sends push notifications from WordPress to your mobile app — automatically on post publish or manually via a focused notification composer — using Google's Firebase Cloud Messaging HTTP v1 API.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv2-green)

> Firebase, Firebase Cloud Messaging and Google are trademarks of Google LLC. This plugin is an independent, third-party integration and is not affiliated with, endorsed by, or sponsored by Google LLC.

---

## How it works

```
WordPress post published
        │
        ▼
  Primary category
        │
        ▼
  Category → Topic map  ──────────────▶  Topic name  ──▶  Mobile apps
  (Settings page)                        e.g. "news"       subscribed to
                                                           that topic
```

The plugin uses **topic subscriptions** — your mobile app calls `subscribeToTopic()` once; the plugin sends to the topic name. No device tokens stored, no PII, no extra database tables.

---

## Features

- **Automatic push** when a post is published — primary category maps to a topic name
- **Notification composer** — dedicated custom post type with:
  - Live phone preview as you type
  - Character counters (65 title / 140 body)
  - Topic selector
  - Optional deep link (any custom URL scheme)
- **FCM HTTP v1** with OAuth2 JWT authentication (RS256)
- **Zero external PHP dependencies** — WordPress HTTP API + PHP OpenSSL only
- **Service account JSON** stored outside the database (`wp-content/uploads/rafaelmarreca-push-private/`, `.htaccess` deny + chmod 600)
- **Idempotent dispatch** — republishing a post never resends
- OAuth2 access token cached for 55 minutes

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| PHP extension | `openssl` (bundled on most hosts) |
| Firebase project | Cloud Messaging enabled |

---

## Installation

1. Download or clone this repository into `wp-content/plugins/rafaelmarreca-push-dispatch/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Settings → RafaelMarreca Push Dispatch**.
4. Upload your Firebase service account JSON.
5. Configure the category → topic map.
6. Click **Send test** to verify everything works.

### Getting the service account JSON

In the [Firebase Console](https://console.firebase.google.com):

```
⚙ Project settings → Service accounts → Generate new private key
```

Download the `.json` file and upload it in the plugin settings. The file is stored at `wp-content/uploads/rafaelmarreca-push-private/` — never in the database.

---

## Configuration

### Category → Topic map

Each WordPress category can map to a topic name. When a post is published, the plugin reads the post's primary category and sends to the corresponding topic.

| WordPress category | Topic name | Who receives |
|---|---|---|
| News | `news` | App users subscribed to `news` |
| Events | `events` | App users subscribed to `events` |
| *(unmapped)* | `all` (default) | App users subscribed to `all` |

### Mobile app setup

Your app needs to subscribe to topics to receive notifications:

```js
// React Native (Firebase SDK)
await messaging().subscribeToTopic('news');
await messaging().subscribeToTopic('all');
```

```swift
// iOS (Swift)
Messaging.messaging().subscribe(toTopic: "news")
```

```kotlin
// Android (Kotlin)
FirebaseMessaging.getInstance().subscribeToTopic("news")
```

### Android notification channel

The plugin sends to the Android channel `rafaelmarreca-push`. Create a matching channel in your app:

```kotlin
val channel = NotificationChannel(
    "rafaelmarreca-push",
    "Push Notifications",
    NotificationManager.IMPORTANCE_HIGH
)
```

### Deep links

The notification composer accepts any [RFC 3986](https://www.rfc-editor.org/rfc/rfc3986) custom URL scheme as a deep link:

```
myapp://section/42
myapp://profile/123
```

The value is passed in the FCM `data` payload as `deep_link` — your app handles the navigation.

---

## Sending manual notifications

Go to **Notifications → New Notification** in the WordPress admin. The composer shows:

- A live **phone preview** that updates as you type
- **Character counters** with colour-coded progress bars
- A **topic selector** populated from your configured topics
- An optional **deep link** field

Publish the post to send. Re-publishing the same notification never resends (idempotent).

---

## Payload structure

```json
{
  "message": {
    "topic": "news",
    "notification": {
      "title": "Post title (max 65 chars)",
      "body": "Post excerpt (max 140 chars)"
    },
    "data": {
      "type": "post",
      "post_id": "42",
      "slug": "my-post-slug",
      "category_id": "3"
    },
    "android": {
      "priority": "high",
      "notification": { "channel_id": "rafaelmarreca-push" }
    },
    "apns": {
      "payload": { "aps": { "sound": "default" } }
    }
  }
}
```

For manual notifications (composer), `data.type` is `"notification"` and `data.deep_link` is included when set.

---

## External services

This plugin relies on two external services operated by Google LLC.

### Google OAuth2 — `https://oauth2.googleapis.com/token`

Used to exchange a locally-signed JWT for a short-lived access token (cached for 55 minutes). Called on the first send and once per token expiry. No WordPress user data is sent — only the JWT assertion derived from your service account's `client_email`.

Terms: [policies.google.com/terms](https://policies.google.com/terms) · Privacy: [policies.google.com/privacy](https://policies.google.com/privacy)

### Firebase Cloud Messaging — `https://fcm.googleapis.com/v1/projects/{project_id}/messages:send`

Used to publish the notification to a topic. Called every time a notification is dispatched (post publish with auto-send, composer publish, or "Send test"). The request body contains the topic name, the title/body of the notification, optional deep link, and the post ID/slug/category ID as identifiers. No WordPress user data, IP addresses, or device identifiers are sent.

Terms: [policies.google.com/terms](https://policies.google.com/terms) · Firebase Terms: [firebase.google.com/terms](https://firebase.google.com/terms) · Privacy: [policies.google.com/privacy](https://policies.google.com/privacy)

---

## Security

### Service account key

> **⚠️ The service account JSON grants Firebase Cloud Messaging access for your project. Treat it like a password.**

| What the plugin does | What you must do |
|---|---|
| Stores the file in `wp-content/uploads/rafaelmarreca-push-private/` | **Never commit** the file to version control |
| Creates `.htaccess` with `Deny from all` | If your server runs **Nginx**, add a deny rule manually (see below) |
| Sets file permissions to `chmod 600` | Keep your WordPress server and `wp-content` directory non-publicly writable |
| Never writes the key content to the database | Verify your backup system does not expose `wp-content/uploads/` publicly |

**If the key is compromised:** go to [Firebase Console → Project settings → Service accounts](https://console.firebase.google.com) and immediately revoke/regenerate the key. Then re-upload the new JSON in the plugin settings.

#### Nginx users

Apache's `.htaccess` has no effect on Nginx. Add this block to your server config to protect the directory:

```nginx
location ~* /wp-content/uploads/rafaelmarreca-push-private/ {
    deny all;
    return 404;
}
```

#### What the service account can do

The plugin requests only the `firebase.messaging` OAuth2 scope:

```
https://www.googleapis.com/auth/firebase.messaging
```

This allows sending messages via Firebase Cloud Messaging. It does **not** grant access to Firestore, Storage, Auth, or any other Firebase service. If your project requires a more restricted key, create a dedicated service account in Google Cloud IAM with only the **Firebase Cloud Messaging API** role.

### WordPress permissions

Only users with the `manage_options` capability (Administrators) can access the plugin settings or the notification composer. Standard editors and authors cannot send notifications or view credentials.

---

## File structure

```
rafaelmarreca-push-dispatch/
├── rafaelmarreca-push-dispatch.php   # Plugin header & bootstrap
├── readme.txt                        # WordPress.org readme
├── uninstall.php                     # Cleanup on plugin deletion
├── assets/
│   ├── admin.css                     # Notification composer styles
│   └── admin.js                      # Char counters, phone preview, JS interactions
└── includes/
    ├── class-fcm-push.php            # Bootstrap: boots all modules
    ├── class-fcm-client.php          # FCM HTTP v1 client (JWT + OAuth2)
    ├── class-dispatcher.php          # Decides when/what to send
    ├── class-cpt.php                 # Custom post type + composer UI
    └── class-settings.php            # Settings page
```

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) — same as WordPress.

---

## Author

[Rafael Marreca](https://github.com/rafael145a)
