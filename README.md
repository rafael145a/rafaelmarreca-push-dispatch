# FCM Push Notify

> Send Firebase Cloud Messaging push notifications from WordPress to your mobile app — automatically on post publish or manually via a focused notification composer.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv2-green)
![FCM](https://img.shields.io/badge/FCM-HTTP%20v1-f57c00?logo=firebase&logoColor=white)

---

## How it works

```
WordPress post published
        │
        ▼
  Primary category
        │
        ▼
  Category → Topic map  ──────────────▶  FCM topic  ──▶  Mobile apps
  (Settings page)                        e.g. "news"       subscribed to
                                                           that topic
```

The plugin uses **FCM topic subscriptions** — your mobile app calls `subscribeToTopic()` once; the plugin sends to the topic name. No device tokens stored, no PII, no extra database tables.

---

## Features

- **Automatic push** when a post is published — primary category maps to an FCM topic
- **Notification composer** — dedicated custom post type with:
  - Live phone preview as you type
  - Character counters (65 title / 140 body)
  - Topic selector
  - Optional deep link (any custom URL scheme)
- **FCM HTTP v1** with OAuth2 JWT authentication (RS256)
- **Zero external dependencies** — WordPress HTTP API + PHP OpenSSL only
- **Service account JSON** stored outside the database (`wp-content/uploads/fcm-push-private/`, `.htaccess` deny + chmod 600)
- **Idempotent dispatch** — republishing a post never resends
- OAuth2 access token cached for 55 minutes

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| PHP extension | `openssl` (bundled on most hosts) |
| Firebase project | FCM enabled |

---

## Installation

1. Download or clone this repository into `wp-content/plugins/fcm-push-notify/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Settings → FCM Push Notify**.
4. Upload your Firebase service account JSON.
5. Configure the category → topic map.
6. Click **Send test** to verify everything works.

### Getting the service account JSON

In the [Firebase Console](https://console.firebase.google.com):

```
⚙ Project settings → Service accounts → Generate new private key
```

Download the `.json` file and upload it in the plugin settings. The file is stored at `wp-content/uploads/fcm-push-private/` — never in the database.

---

## Configuration

### Category → Topic map

Each WordPress category can map to an FCM topic. When a post is published, the plugin reads the post's primary category and sends to the corresponding topic.

| WordPress category | FCM topic | Who receives |
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

The plugin sends to the Android channel `fcm-push-notify`. Create a matching channel in your app:

```kotlin
val channel = NotificationChannel(
    "fcm-push-notify",
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

## FCM payload structure

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
      "notification": { "channel_id": "fcm-push-notify" }
    },
    "apns": {
      "payload": { "aps": { "sound": "default" } }
    }
  }
}
```

For manual notifications (composer), `data.type` is `"notification"` and `data.deep_link` is included when set.

---

## File structure

```
fcm-push-notify/
├── fcm-push-notify.php          # Plugin header & bootstrap
├── readme.txt                   # WordPress.org readme
├── uninstall.php                # Cleanup on plugin deletion
├── assets/
│   ├── admin.css                # Notification composer styles
│   └── admin.js                 # Char counters, phone preview, JS interactions
└── includes/
    ├── class-fcm-push.php       # Bootstrap: boots all modules
    ├── class-fcm-client.php     # FCM HTTP v1 client (JWT + OAuth2)
    ├── class-dispatcher.php     # Decides when/what to send
    ├── class-cpt.php            # Custom post type + composer UI
    └── class-settings.php       # Settings page
```

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) — same as WordPress.

---

## Author

[Rafael Marreca](https://github.com/rafael145a)
