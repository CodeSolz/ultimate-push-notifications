### Unreleased ###

**New**
- **Upgrade to Pro:** an entry at the end of the UPush Notifier menu and a first link on the Plugins screen ("Upgrade to Pro"), plus "Pro features" under the plugin's description — each opens the product page in a new tab, tagged with where it was clicked, and all of them go away when Pro is active. `pro\Upgrade`, filters `upn_pro_active`, `upn_upgrade_url` (now also given the source) and `upn_row_meta`.
- **readme:** what Pro adds, in one section — SMS / WhatsApp / Telegram for customers, carriers for the team, segments, scheduling, A/B, store automations, attribution, reports and the rest — with links to the product page. The GitHub README describes the current (Web Push) plugin instead of the Firebase one.
- **Seams for Pro's customer channels:** `upn_automation_unreached` (a rule applied but push had nobody — a guest's order) and a public `Engine::render()`; WooCommerce order events carry the order's id in their context and a `{customer_phone}` merge tag; entitlement key `channels`.

**Fixed**
- The Plugins-screen links used another plugin's text domain (so they could not be translated) and its `rtafar_row_meta` filter; they now use this plugin's. "Notifications Settings" (a member's own page) became "Compose" next to "Settings".

### Version: 1.6.2 ( September 16, 2026 ) ###

**New**
- **Automation triggers — 29, up from 16.** WordPress: *Post or product published* (any public post type; the type is a rule param, the author a person, the featured image travels with it) and *Post or product updated* (edits to published content; saves that change nothing a reader sees are ignored). WooCommerce: *Price drop* (the price before the save is read from the database, so a drop is a real drop; "drop of at least" 5 / 10 / 20 / 30 / 50 %; `{price}` `{old_price}` `{drop}`) and *Back in stock* (a variation announces its parent; once per product per day). BuddyPress: *New follower* and *Someone you follow publishes a post* — for BuddyBoss Platform and the BuddyPress Follow plugin, shown only when following exists; the followers are the audience. New **Membership** group, each trigger present only while its plugin is active: Paid Memberships Pro (level changed, level as a param), MemberPress (transaction completed), Restrict Content Pro (activated, expired), WooCommerce Memberships (status changed, status as a param), Ultimate Member (approved), LearnDash (course completed). The member is the person; `{member_name}` `{member_email}` `{member_url}` plus the plan, level or course.
- **Home Screen app.** Turn it on under Subscribe Prompt: the plugin serves a web app manifest at `/?upn_manifest=1` from your Site Icon (192 / 512), short name, colours and display mode, and adds the manifest link and Apple meta tags to every page, so iPhone and iPad visitors (iOS 16.4+) can add the site and subscribe. The soft-ask bar detects an iPhone outside the Home Screen and shows add-to-Home-Screen guidance instead of a prompt that cannot work. Steps aside when SuperPWA, PWA or PWA for WP is present. Health check *Home Screen app*.
- **Menu with tabs.** Six entries instead of one per screen — Compose, Automations, Subscribers (All Registered Devices · Register My Device · Set Notifications), Subscribe Prompt, Health, Settings (App Config) — each screen a tab under its group. Every `admin.php?page=cs-upn-…` link still works, and the group entry stays lit on any tab.
- **Subtitles** on every screen heading, in the same style as App Configuration.

**Changed**
- **Every screen added since 1.5 uses the plugin's own panel layout** — the gradient heading, hints well, label / input rows, section titles, submit bar and footer that App Configuration has — instead of bare WordPress form tables. The admin stylesheet now loads on every plugin screen (it loaded on three before). Inline width caps are gone: the panel is the container, tables scroll inside their own wrapper on a narrow screen, and Compose's two-column layout stacks below tablet width.

**Pro (the next step each free feature points at)**
- Every new trigger names, in the rule editor, the Pro capability that completes its workflow while it is locked: per-shopper price and stock alerts (Store Automations), conditions, digests, frequency caps, carriers, goals, a welcome series. The free plugin makes the first send of every workflow; Pro finishes it.
- Entitlement keys added for the Pro plugin 2.0: `network`, `commerce`, `pwa.install`, `ai.segments`, `risk`. Locked panels with real site figures on Automations (store automations, shown with WooCommerce), Compose (opt-out risk, segment discovery), Subscribe Prompt (install prompt), Health (multisite, shown on a network).

**Fixed**
- WooCommerce no longer lists the plugin as incompatible with High-Performance Order Storage or block checkout: compatibility is declared (`before_woocommerce_init`); orders were only ever read through WooCommerce's own APIs.
- The top-level menu entry could fatal if opened directly (its callback was a leftover string from another product); it now opens the first screen the user may see.

**Developer**
- `admin\builders\Layout` (`open` / `close` / `section` / `field` / `submit_bar` / `tabs` / `footer`; `upn_admin_footer_line`) and `admin\builders\Screens` (`register` / `hide` / `page` / `in_group` / `hook` / `tabs_html`; `upn_admin_groups`) — extensions register screens into the same groups and get the same chrome.
- `Trigger::next()` — a trigger's "what Pro adds" hint; `WordPressTriggers::post_event()` and `post_type_options()` for triggers built on posts; `BuddyPressTriggers::followers()`.
- `pwa\Manifest` (`upn_pwa_manifest`, `upn_pwa_deferred`), `UPN_WebPush.needsInstall`, health check id `app`.
- Tests: 1085 checks across 16 standalone suites (`composer test`); `tests/stubs/LayoutStub.php` for suites that render a screen.

### Version: 1.6.1 ( September 14, 2026 ) ###
- **New:** A delivery can be held or dropped per subscriber just before it goes out (`upn_delivery_hold`, `upn_delivery_skip`); the queue waits without spending an attempt (`JobOutcome::defer()`). Test and preview sends are never gated. Held-back recipients are counted apart from failures in the log (`skipped_count`, DB 1.6.1) and shown on the Health page.
- **Changed:** the service worker's click beacon sends same-origin credentials, so its response can set a first-party cookie (Pro's revenue attribution ties a later order to the click). Entitlement key `analytics.attribution`.
- **Developer:** `upn_subscription_created` fires for a genuinely new subscription (not a key refresh); `DeliveryLog::add_recipients()`; `SubscriptionStore::count_since_days()`; entitlement keys `drip`, `ab`, `analytics.goals`, `sto`, `preferences`, `carriers`, `api`, `roles`, `reports`, `whitelabel`, `network`, `commerce`. `RulesPage::capability()` (`upn_automations_capability`) like Compose's, used by the menu too. The bell fires a cancellable `upn:bell-click` event before it toggles; `window.UPN_WebPush.post()` and `.endpoint()` are exported. `Composer::sanitize()` carries an `extra` bucket (`upn_sanitize_fields_extra`) into the send options — never into the device payload. `upn_automation_dispatch` lets an extension take over a rule's send; rules carry an `extra` bucket (`upn_automation_sanitize_extra`) with an editor slot (`upn_rule_editor_extras`). `Subscription::$timezone`. Entitlement keys `cadence`, `automations.digest`.
- **Major:** Anonymous subscribers with full metadata (browser, OS, device, locale, time zone, last seen, consent). No subscriber cap.
- **Major:** Compose screen: audience with live count, preview to own device, background fan-out.
- **Major:** Event automations rebuilt as trigger → audience → message rules; 16 triggers (WordPress, WooCommerce, BuddyPress, CF7); explicit audiences; actor excluded by default; Free runs 3 at a time.
- **Fixed by the rebuild:** Woo product-author-only recipients; BuddyPress activity events notifying the actor; `user_register` targeting the new user; CF7 default-field-names-only.
- **New:** Push on publish. Subscribe Prompt (soft-ask bar, bell, shortcode, block). Merge tag registry with per-trigger event tags. Click tracking + CTR. Privacy export/erase. Rebuilt subscriber list with views, sorting and search.
- **Changed:** Set Notifications is a receive/mute list; copy lives on the rule. `upn_notifications` table no longer created.
- **Fix:** Queue books its next tick for the earliest future job (long backoffs no longer stall until an admin visit); a sooner tick request moves a later booking.
- **Developer:** `Entitlements::can()` + `upn_can`; `upn_automation_*`, `upn_autopush_fields`, `upn_before_broadcast`, `upn_allow_anonymous_subscriptions`, `upn_notification_clicked`, `upn_sanitize_audience`, `upn_audience_where`, `upn_ajax_handlers`, `upn_queue_housekeeping`, `window.UPN_Compose`. Subscriber `last_click_on` column.
- **Major:** Native Web Push (VAPID) is the default transport. One-click setup, no Firebase account. Encryption verified against the RFC 8291 test vector.
- **Major:** Background delivery queue with atomic claiming, exponential backoff, Retry-After support; Action Scheduler or WP-Cron.
- **New:** Health page with live checks, plain explanations, fixes, and an explained score.
- **New:** Delivery log with per-recipient outcomes.
- **New:** Root-scope service worker at `/?upn_sw=1`; automatic re-subscription on `pushsubscriptionchange`.
- **New:** `data-upn-subscribe` attribute and `window.UPN_WebPush` API for themes.
- **Changed:** Devices addressed by id, deduplicated on full endpoint/token.
- **Changed:** Legacy Firebase transport retained for migration; reports the shutdown date.
- **Developer:** Extension points `upn_transports`, `upn_before_send`, `upn_after_send`, `upn_subscription_pruned`, `upn_delivery_logged`, `upn_queue_job_handlers`, `upn_queue_enabled`, `upn_health_checks`.
- **Requires:** PHP 7.4+.
- **Security:** Device tokens are now bound to the authenticated user server-side, not to an account id supplied in the request body. All sites should update.
- **Security:** Fixed the recursive input sanitizer, which returned array input unsanitized.
- **Security:** Fixed two SQL injections in the registered-devices list; one was reachable by any subscriber.
- **Security:** The AJAX endpoint now dispatches only to an allow-list of handlers, each gated by a capability, instead of reflectively invoking any class named in the request.
- **Security:** The BuddyPress notification-settings form now verifies its nonce.
- **Security:** Bulk device deletion is scoped to the current user unless the caller can `manage_options`.
- **Security:** Removed a Firebase configuration committed to the repository and shipped in the package.
- **Fix:** The activation routine was registered as a deactivation hook; schema changes could never reach an existing install. Tables now install and upgrade via `dbDelta()` behind a version check.
- **Fix:** Added the missing indexes on the device and preference tables.
- **Fix:** The devices list no longer fatals on a row whose user account was deleted.
- **Removed:** The Events Calendar settings tab, which was wired to no triggers.
- **Removed:** Dokan / WCFM claims that had no corresponding code.
- **Removed:** Dead files shipped in the package, including a duplicate class declaration and the orphaned Firebase v7 SDK.
- **Housekeeping:** Plugin header now declares `Requires PHP: 7.4`, matching readme.txt.

### Version: 1.3.0 ( June 09, 2026 ) ###
- **Major:** Firebase SDK updated from v7.15.5 to v11.0.0 compat.
- **Major:** Service worker updated to use `onBackgroundMessage()`.
- **New:** VAPID Key (Web Push Certificate) field added to App Config.
- **New:** Rich notifications — image support in the push payload.
- **New:** Notification click-to-navigate handling in the service worker.
- **New:** Auto-cleanup of `NotRegistered` / `InvalidRegistration` device tokens.
- **New:** WooCommerce buyers now also receive order status change notifications.
- **Security:** The FCM server key is no longer exposed to browser JavaScript.

### Version: 1.2.0 ( September 29, 2025 ) ###
- **Update:** Security patch updated

### Version: 1.1.9 ( June 02, 2025 ) ###
- **Update:** Security patch updated

### Version: 1.1.8 ( January 07, 2025 ) ###
- **Update:** Updated to WordPress & WooCommerce latest compatibility

### Version: 1.1.7 ( April 13, 2024 ) ###
- **Update:** Updated to WordPress & WooCommerce latest compatibility

### Version: 1.1.6 ( January 09, 2024 ) ###
- **Update:** Updated to WordPress & WooCommerce latest compatibility

### Version: 1.1.5 ( September 09, 2023 ) ###
- **Update:** WordPress >= 6.3 & WooCommerce >= 8.0 version compatible

### Version: 1.1.4 ( April 06, 2023 ) ###
- **Improvement:** WordPress 6.2 & WooCommerce > 7.5 compatible

### Version: 1.1.3 ( September 07, 2022 ) ###
- **Fix:** Small bug fixed - page loading
- **Improvement:** Upgrade on APP connecting time

### Version: 1.1.2 ( September 07, 2022 ) ###
- **Fix:** Duplicate device registration fixed
- **Fix:** Empty configuration bug fixed

### Version: 1.1.1 ( August 30, 2022 ) ###
- **New:** Contact Form 7 - Receive push notification when someone submits the form
- **Fix:** Major bug fixed - page loading

### Version: 1.1.0 ( August 19, 2022 ) ###
- **New:** Receive push notification on - friend request cancelled
- **New:** Receive push notification on - new activity published
- **New:** Receive push notification on - new custom activity post type published
- **New:** Receive push notification on - new custom activity post type updated
- **New:** Receive push notification on - new custom activity post type deleted
- **New:** Receive push notification on - new comment on post / activity status
- **New:** Receive push notification on - new message received
- **New:** Receive push notification on - new group invitation received
- **New:** Receive push notification on - group details updated

### Version: 1.0.9 ( August 08, 2022 ) ###
- **New:** Any users can receive push notifications
- **New:** New settings panel on BuddyPress front-end (BuddyPress users can set to receive notifications)
- **New:** Any users have access to the backend section to set up notifications for themselves
- **New:** Full new interface for notifications settings section
- **Fix:** BuddyPress notification minor bug fixed

### Version: 1.0.8 ###
- **Improvement:** BuddyPress notification feature updated

### Version: 1.0.7 ###
- **New:** BuddyPress notifications added (new friend request, friend request accepted, friend request rejected)
- **Update:** SweetAlert2 updated to latest version
- **Improvement:** WordPress 6.0 & PHP 8 compatible

### Version: 1.0.6 ###
- **Improvement:** WordPress 5.9 compatible

### Version: 1.0.5 ###
- **Improvement:** WordPress 5.8 compatible

### Version: 1.0.4 ###
- **Improvement:** Improvement of configuration
- **Update:** MeasurementID field is now optional

### Version: 1.0.3 ###
- **Adjustment:** Updated to latest WordPress release

### Version: 1.0.2 ###
- **Improvement:** Improvement of configuration
- **Adjustment:** Updated to latest WordPress release

### Version: 1.0.1 ###
- **Improvement:** Improvement of configuration

### Version: 1.0.0 ###
- Initial release.
