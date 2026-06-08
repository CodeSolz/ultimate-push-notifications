=== Ultimate Push Notifications ===
Contributors: CodeSolz, m.tuhin
Tags: push notifications, web push, firebase, woocommerce push notifications, buddypress notifications, desktop notifications, mobile push, fcm, cloud messaging, real-time notifications
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.3.0
Requires PHP: 7.4
WC requires at least: 4.0
WC tested up to: 10.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The most complete web push notification plugin for WordPress. Real-time push alerts for WooCommerce orders, BuddyPress activity, form submissions, and more — powered by Firebase 11.

== Description ==

**Ultimate Push Notifications** is the most complete, privacy-friendly push notification solution for WordPress websites. Powered by **Firebase Cloud Messaging (FCM) SDK v11**, it delivers real-time alerts directly to your visitors' browsers — on desktop and mobile — even when they are not on your site.

No SMS bills. No email list dependency. Just instant, reliable push notifications that actually reach people.

<blockquote>
Push notifications have 4x higher open rates than email. Keep your customers, community members, and team informed the moment something happens — for free.
</blockquote>

= Why Choose Ultimate Push Notifications? =

Most push notification services charge a monthly fee per subscriber. This plugin uses **Google Firebase** (free tier is generous for most sites) and keeps everything under your control. Your subscriber data stays in your own database. You own your audience.

= Real Problems This Plugin Solves =

**For WooCommerce Store Owners:**
You're losing sales because customers have no idea their order shipped, their payment failed, or their order is ready. And your vendors have no idea someone just bought from them. This plugin fixes all of that — with instant push alerts for every order event.

**For BuddyPress / Community Sites:**
Your community members miss friend requests, new messages, group invites, and activity updates because they're not watching their inbox. Push notifications bring them back the moment something happens — without email fatigue.

**For Site Administrators:**
You need to know the instant a form is submitted, a new user registers, or an event goes live. Real-time push notifications to your browser mean you're always in the loop, even if your email is buried.

**For All Sites:**
Instead of paying for SMS alerts or high-volume email plans, this plugin lets you communicate in real time for free — using the same infrastructure Google uses for Android notifications.

---

= Key Features =

**Firebase 11 — Always Current**
- Powered by Firebase Cloud Messaging (FCM) SDK **version 11** compat
- Supports **VAPID keys** (Web Push Certificate) for modern browser compatibility
- Service Worker handles background notifications even when the browser is closed
- Rich notifications: **show images** inside the push popup
- Click-to-navigate: clicking a notification opens the right page automatically
- Auto-cleanup: invalid / expired device tokens are **removed automatically** — no stale bloat

**Per-User Control**
- Users choose which notification types they want to receive — no spam
- Users can customize the notification title and message text
- BuddyPress members manage their preferences from their profile page
- Zero tracking: no third-party analytics, no subscriber IDs sent anywhere

**WooCommerce — Complete Order Lifecycle**
- Seller alert when a product is sold (payment complete)
- Seller alert when a product is added to cart
- **Buyer AND seller** alert when order status changes (processing, shipped, complete, on-hold, etc.)
- Supports multi-vendor platforms: Dokan, WCFM

**BuddyPress — Every Social Interaction**
- New message received
- Friend request sent / accepted / rejected / cancelled
- New activity post or status update published
- Comment on a post or activity
- New group invitation received
- Group details updated
- Custom activity post types: published, updated, deleted

**Contact Form 7**
- Admin receives instant push notification when any CF7 form is submitted — never miss a lead

**WordPress Core**
- Admin notified the moment a new user registers

**The Events Calendar**
- Integration ready for event-based notifications

**Device & Subscriber Management**
- Each user's devices are tracked individually
- View all registered devices in the admin panel
- Test notifications directly from the admin — verify delivery before going live
- Notification log table tracks delivery history (success / failure counts per token)

---

= How It Works =

1. You create a free Firebase project at [console.firebase.google.com](https://console.firebase.google.com/)
2. Enter your Firebase config and VAPID key in the plugin's **App Config** screen
3. Users visit your site and click **Allow** on the browser permission prompt
4. Their device is registered — push notifications start flowing instantly

No app stores. No app to install. Works in Chrome, Firefox, Edge, Safari (with Web Push support), and most modern browsers.

= Security =

- FCM server key is stored server-side only — never exposed to browser JavaScript
- All user inputs are sanitized through WordPress standards
- AJAX endpoints use nonce verification (SECURE_AUTH_SALT)
- Capability checks enforce admin-only access to sensitive settings
- VAPID key authentication ensures only your server can send notifications

---

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* **SSL (HTTPS) required** — browsers block push notification registration on non-secure sites
* A free Firebase account at [console.firebase.google.com](https://console.firebase.google.com/)
* Firebase VAPID key (Web Push Certificate) from your Firebase project settings

= Multi-Device & Testing =

* One user can register multiple browsers / devices
* For testing: use different browsers or incognito windows logged in as different users
* Use the **Register My Device** page to register your own device and test with one click

---

= Getting Started (Quick Setup) =

1. Install and activate the plugin
2. Go to **UPush Notifier → App Config**
3. Create a Firebase project and paste your config (apiKey, authDomain, projectId, etc.)
4. Paste your **VAPID Key** (from Firebase Console → Project Settings → Cloud Messaging → Web Push certificates → Generate key pair)
5. Paste your **Server Key** (from Firebase Console → Project Settings → Cloud Messaging → Legacy API section)
6. Save the config
7. Go to **UPush Notifier → Register My Device** and click Allow
8. Send a test notification — you should receive it instantly

Full step-by-step documentation:

* [Create a Firebase Application](https://docs.codesolz.net/ultimate-push-notifications/how-to-create-firebase-application/create-firebase-application/)
* [Setup App Config](https://docs.codesolz.net/ultimate-push-notifications/how-to-create-firebase-application/setup-app-config/)
* [Configure Notifications](https://docs.codesolz.net/ultimate-push-notifications/how-to-create-firebase-application/set-notification/)
* [Register a Device](https://docs.codesolz.net/ultimate-push-notifications/how-to-create-firebase-application/register-a-device-to-get-notification/)

= Video Guides =

[youtube https://www.youtube.com/watch?v=Vc1FuG1np5k]

[youtube https://www.youtube.com/watch?v=TARCZGGlG5k]

---

= Notification Reference =

**WooCommerce**
- `{first_name}` `{last_name}` `{full_name}` — customer name
- `{total}` — order total with currency
- `{product_title}` — product name
- `{price}` — product price
- `{order_id}` — order number
- `{status_from}` `{status_to}` — order status transition

**BuddyPress**
- All standard BuddyPress activity and messaging events

---

= Forum & Support =

<blockquote>
For support, feature requests, and bug reports:

* Visit [codesolz.net](https://codesolz.net/?utm_source=wordpress.org&utm_medium=README&utm_campaign=ultimate-push-notifications) for instant support
* Email: [support@codesolz.net](mailto:support@codesolz.net)
* GitHub: [github.com/CodeSolz/ultimate-push-notifications](https://github.com/CodeSolz/ultimate-push-notifications)
* Forum: [forum.codesolz.net](https://forum.codesolz.net/?utm_source=wordpress.org&utm_medium=README&utm_campaign=ultimate-push-notifications)
</blockquote>

== Installation ==

1. Upload the *ultimate-push-notifications* folder to the */wp-content/plugins/* directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **UPush Notifier → App Config** and enter your Firebase credentials
4. Go to **UPush Notifier → Register My Device** to register your browser
5. Send a test notification to confirm everything is working

== Frequently Asked Questions ==

= Does this work without SSL? =

No. Push notification APIs require HTTPS. Your site must have an active SSL certificate. Most hosting providers offer free SSL via Let's Encrypt.

= Is this free? =

Yes — the plugin is free and open source. Firebase's free tier (Spark plan) supports unlimited push notifications. You only pay Firebase if you need other paid Firebase services.

= Do I need to install an app? =

No. Web push notifications work directly in the browser — Chrome, Firefox, Edge, and modern Safari. No app download required.

= What is a VAPID key and do I need it? =

VAPID (Voluntary Application Server Identification) keys are required by modern browsers for web push. Without it, token registration may fail in newer browser versions. Get yours from Firebase Console → Project Settings → Cloud Messaging → Web Push certificates → Generate key pair.

= My notifications stopped working after updating Firebase. What changed? =

Firebase SDK v11 uses `onBackgroundMessage()` (v7 used `setBackgroundMessageHandler()`) and no longer needs `useServiceWorker()`. This plugin (v1.3.0+) is fully updated for Firebase 11. If you upgraded from a very old version, re-save your App Config to regenerate the JS files.

= Can users opt out? =

Yes. Users can uncheck notification types in **UPush Notifier → Set Notifications** at any time. BuddyPress users can manage preferences from their profile notifications page.

= What happens to invalid device tokens? =

Starting in v1.3.0, the plugin automatically removes tokens when FCM returns `NotRegistered` or `InvalidRegistration`. This keeps your subscriber list clean without manual maintenance.

= WooCommerce: do buyers get order notifications? =

Yes — from v1.3.0, both the seller and the buyer receive push notifications when an order status changes, as long as they have registered their device and enabled the notification type.

= Is subscriber data shared with anyone? =

No. All device tokens and user preferences are stored in your WordPress database. Nothing is sent to CodeSolz servers.

== Screenshots ==

1. App Configuration — Firebase setup with VAPID key support
2. Notification Settings — per-type, per-user control
3. Notification Settings — custom title and message text
4. BuddyPress Frontend — notification preferences in user profile
5. User Notification Preferences — backend panel
6. Register My Device — one-click device registration and test
7. All Registered Devices — subscriber management
8. Push Notification Example — desktop
9. Push Notification Example — mobile (rich notification with image)

== Changelog ==

= Version: 1.3.0 ( June 09, 2026 ) =
* **Major:** Firebase SDK updated from v7.15.5 to **v11.0.0 compat** — fixes compatibility with modern browsers
* **Major:** Service worker updated to use `onBackgroundMessage()` — replaces removed `setBackgroundMessageHandler()`
* **New:** VAPID Key (Web Push Certificate) field added to App Config — required for modern browser push registration
* **New:** Rich notifications — image support added to push notification payload (desktop & mobile)
* **New:** Notification click-to-navigate — clicking a notification now opens the correct URL (handled in service worker)
* **New:** Auto-cleanup of invalid device tokens — `NotRegistered` / `InvalidRegistration` tokens are deleted automatically
* **New:** Notification delivery log database table (`upn_notification_log`) for tracking send history
* **New:** WooCommerce **buyer** (customer) now also receives order status change notifications — not just the seller
* **Security:** FCM server key is no longer exposed to browser JavaScript via `UPN_Notifier` localized object
* **Fix:** WooCommerce `build_notific_on_payment_complete()` — undefined variable bug fixed (order checked before use)
* **Fix:** `prepare_send_notifications()` — missing `image` field added to payload, `click_action` falls back to `site_url()`
* **Fix:** Return value consistency — all notification builders now return empty array instead of undefined on no-op
* **Improvement:** Firebase scripts now load from Google CDN with explicit dependency chain for correct load order
* **Improvement:** `measurementId` and `vapidKey` are treated as optional fields — saving config no longer blocks on them
* **Compatibility:** WordPress 6.8 tested, WooCommerce 10.2 tested

= Version: 1.2.0 ( September 29, 2025 ) =
* **Update:** Security patch updated

= Version: 1.1.9 ( June 02, 2025 ) =
* **Update:** Security patch updated

= Version: 1.1.8 ( January 07, 2025 ) =
* **Update:** Updated to WordPress & WooCommerce latest compatibility

= Version: 1.1.7 ( April 13, 2024 ) =
* **Update:** Updated to WordPress & WooCommerce latest compatibility

= Version: 1.1.6 ( January 09, 2024 ) =
* **Update:** Updated to WordPress & WooCommerce latest compatibility

= Version: 1.1.5 ( September 09, 2023 ) =
* **Update:** WordPress >= 6.3 & WooCommerce >= 8.0 compatible

= Version: 1.1.4 ( April 06, 2023 ) =
* **Improvement:** WordPress 6.2 & WooCommerce > 7.5 compatible

= Version: 1.1.3 ( February 15, 2023 ) =
* **Improvement:** WordPress 6.1 compatible

= Version: 1.1.0 ( October 2022 ) =
* **New:** BuddyPress frontend notification preferences page
* **New:** Contact Form 7 integration

= Version: 1.0.0 =
* Initial release — Firebase FCM integration, WooCommerce and BuddyPress support
