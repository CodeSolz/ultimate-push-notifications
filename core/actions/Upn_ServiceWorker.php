<?php namespace UltimatePushNotifications\actions;

/**
 * Serves the Web Push service worker from the site root.
 *
 * A service worker's scope is bounded by the path it was fetched from. The
 * old worker lived at /wp-content/plugins/…/js/, which restricted it to that
 * directory and meant it could never act for the site. Serving it from
 * home_url('/?upn_sw=1') gives it root scope without a rewrite rule, so it
 * works on every permalink structure and needs no flush.
 *
 * @package Action
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\transport\DeliveryLog;
use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\Vapid;

class Upn_ServiceWorker {

	/**
	 * Query variable that requests the worker.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'upn_sw';

	/**
	 * Query variable the worker beacons a click to.
	 *
	 * @var string
	 */
	const CLICK_VAR = 'upn_click';

	function __construct() {
		// Early enough to run before any theme output, late enough for the
		// options table and the plugin's own classes to be available.
		add_action( 'init', array( $this, 'maybe_serve' ), 1 );
	}

	/**
	 * The URL the browser registers.
	 *
	 * @return string
	 */
	public static function url() {
		return \add_query_arg( self::QUERY_VAR, '1', \home_url( '/' ) );
	}

	/**
	 * Serve the worker and exit when it was requested.
	 *
	 * @return void
	 */
	public function maybe_serve() {
		if ( isset( $_GET[ self::CLICK_VAR ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- a beacon from the service worker; it can only increment a counter within a 30-day window.
			$endpoint = isset( $_POST['endpoint'] ) ? \esc_url_raw( \wp_unslash( $_POST['endpoint'] ) ) : '';
			self::record_click( (int) $_GET[ self::CLICK_VAR ], $endpoint );
			\nocache_headers();
			\status_header( 204 );
			exit;
		}

		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		\nocache_headers();
		\header( 'Content-Type: application/javascript; charset=utf-8' );
		\header( 'Service-Worker-Allowed: /' );
		\header( 'X-Content-Type-Options: nosniff' );

		echo self::source(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript, values are JSON-encoded below.
		exit;
	}

	/**
	 * The URL the worker POSTs a click to, with the log id appended.
	 *
	 * @return string
	 */
	public static function click_url() {
		return \add_query_arg( self::CLICK_VAR, '', \home_url( '/' ) );
	}

	/**
	 * Count a click against a delivery log row.
	 *
	 * The worker cannot carry a nonce that outlives a session and may be
	 * running for an anonymous subscriber, so this endpoint is unauthenticated
	 * by necessity. What bounds it: the id must name a row sent within the last
	 * 30 days, the increment is a single bounded UPDATE, and the worst an abuser
	 * achieves is an inflated click count on their own notification.
	 *
	 * @param int $log_id
	 * @return bool
	 */
	public static function record_click( $log_id, $endpoint = '' ) {
		global $wpdb;

		$log_id = (int) $log_id;
		if ( $log_id <= 0 ) {
			return false;
		}

		$table  = DeliveryLog::table();
		$cutoff = \gmdate( 'Y-m-d H:i:s', \time() - ( 30 * DAY_IN_SECONDS ) );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET click_count = click_count + 1 WHERE id = %d AND sent_at >= %s",
				$log_id,
				$cutoff
			)
		);

		if ( $updated ) {
			$subscription_id = 0;
			if ( '' !== (string) $endpoint ) {
				$subscription_id = SubscriptionStore::touch_click( (string) $endpoint );
			}

			/**
			 * Fires when a notification click is recorded.
			 *
			 * @param int $log_id
			 * @param int $subscription_id 0 when the beacon did not name a known subscription.
			 */
			\do_action( 'upn_notification_clicked', $log_id, $subscription_id );
		}

		return (bool) $updated;
	}

	/**
	 * The worker's JavaScript.
	 *
	 * Kept dependency-free and small. The values baked in are the public VAPID
	 * key (needed to re-subscribe after the push service rotates the
	 * subscription) and the endpoint to report that to.
	 *
	 * @return string
	 */
	public static function source() {
		$config = array(
			'publicKey' => Vapid::get_public_key(),
			'ajaxUrl'   => \admin_url( 'admin-ajax.php' ),
			'nonce'     => \wp_create_nonce( SECURE_AUTH_SALT ),
			'saveMethod' => 'admin\functions\Subscriptions@save',
			'home'      => \home_url( '/' ),
			'clickUrl'  => self::click_url(),
		);

		$json = \wp_json_encode( $config );

		return <<<JS
/* Ultimate Push Notifications — service worker. Generated; do not edit. */
'use strict';

var UPN = {$json};

function urlBase64ToUint8Array(base64String) {
	var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
	var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
	var raw = self.atob(base64);
	var out = new Uint8Array(raw.length);
	for (var i = 0; i < raw.length; ++i) { out[i] = raw.charCodeAt(i); }
	return out;
}

function reportSubscription(subscription) {
	if (!subscription) { return Promise.resolve(); }
	var json = subscription.toJSON();
	var body = new FormData();
	body.append('action', 'upn_ajax');
	body.append('cs_token', UPN.nonce);
	body.append('method', UPN.saveMethod);
	body.append('endpoint', json.endpoint);
	body.append('p256dh', json.keys && json.keys.p256dh ? json.keys.p256dh : '');
	body.append('auth', json.keys && json.keys.auth ? json.keys.auth : '');
	return fetch(UPN.ajaxUrl, { method: 'POST', credentials: 'include', body: body }).catch(function () {});
}

self.addEventListener('install', function () {
	self.skipWaiting();
});

self.addEventListener('activate', function (event) {
	event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
	var data = {};
	if (event.data) {
		try { data = event.data.json(); } catch (e) { data = { title: event.data.text() }; }
	}

	var title = data.title || '';
	var options = {
		body: data.body || '',
		data: { url: data.click_action || UPN.home, logId: data.log_id || 0 }
	};
	if (data.icon)  { options.icon  = data.icon; }
	if (data.image) { options.image = data.image; }
	if (data.badge) { options.badge = data.badge; }
	if (data.tag)   { options.tag   = data.tag; options.renotify = true; }
	if (Array.isArray(data.actions) && data.actions.length) {
		// Buttons: the browser shows at most two. Their URLs ride along in data so a click can route.
		options.actions = data.actions.slice(0, 2).map(function (a) { return { action: a.action, title: a.title }; });
		options.data.actions = {};
		data.actions.slice(0, 2).forEach(function (a) { options.data.actions[a.action] = a.url; });
	}

	event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
	event.notification.close();
	var d     = event.notification.data || {};
	var url   = d.url || UPN.home;
	var logId = d.logId || 0;
	// A button click lands on that button's URL; the notification body on the main one.
	if (event.action && d.actions && d.actions[event.action]) { url = d.actions[event.action]; }

	if (logId && UPN.clickUrl) {
		// Fire-and-forget; keepalive lets it complete even as the worker winds down.
		// The endpoint lets the server stamp the subscriber, not just the send.
		// Same-origin credentials so the response can set a first-party cookie
		// (revenue attribution ties a later order back to this click).
		try {
			self.registration.pushManager.getSubscription().then(function (sub) {
				var body = new FormData(); body.append('endpoint', sub ? sub.endpoint : '');
				return fetch(UPN.clickUrl + logId, { method: 'POST', keepalive: true, credentials: 'same-origin', body: body });
			}).catch(function () {});
		} catch (e) {}
	}

	event.waitUntil(
		self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
			for (var i = 0; i < list.length; i++) {
				if (list[i].url === url && 'focus' in list[i]) { return list[i].focus(); }
			}
			return self.clients.openWindow(url);
		})
	);
});

/*
 * The push service can replace a subscription at any time (key rotation,
 * service migration). Re-subscribe with the same application key and tell
 * the server, or the device silently stops receiving anything.
 */
self.addEventListener('pushsubscriptionchange', function (event) {
	if (!UPN.publicKey) { return; }
	event.waitUntil(
		self.registration.pushManager.subscribe({
			userVisibleOnly: true,
			applicationServerKey: urlBase64ToUint8Array(UPN.publicKey)
		}).then(reportSubscription)
	);
});
JS;
	}

}
