<?php namespace UltimatePushNotifications\health;

use UltimatePushNotifications\actions\Upn_ServiceWorker;
use UltimatePushNotifications\queue\JobRepository;
use UltimatePushNotifications\queue\Runner;
use UltimatePushNotifications\transport\DeliveryLog;
use UltimatePushNotifications\transport\Ec;
use UltimatePushNotifications\transport\Subscription;
use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\TransportFactory;
use UltimatePushNotifications\transport\Vapid;

/**
 * Can this site deliver a push notification right now — and if not, why?
 *
 * "It just stopped working" is the complaint every product in this category
 * accumulates, and this plugin accumulated two years of it. Every check here
 * corresponds to a way that actually happens: no HTTPS, no keys, a host whose
 * OpenSSL cannot generate keys, a service worker that cannot be fetched, cron
 * that never fires, a push service rejecting the VAPID credentials, every
 * device still on the retired Firebase API.
 *
 * The score follows the sibling product's discipline: start at 100, deduct
 * named and bounded amounts, and explain the arithmetic. A score nobody can
 * account for is worse than no score.
 *
 * @package Health
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class HealthCheck {

	const PASS = 'pass';
	const WARN = 'warn';
	const FAIL = 'fail';

	/**
	 * Deduction per check on FAIL. WARN deducts half.
	 *
	 * Weighted by how completely the problem stops delivery: no HTTPS or no
	 * keys means nothing goes out; a stale queue means things go out late.
	 *
	 * @var array<string,int>
	 */
	const WEIGHTS = array(
		'https'          => 40,
		'php'            => 30,
		'crypto'         => 30,
		'vapid'          => 25,
		'transport'      => 25,
		'service_worker' => 15,
		'queue'          => 15,
		'delivery'       => 20,
		'subscribers'    => 10,
		'legacy'         => 10,
		'app'            => 5,
	);

	/**
	 * Run every check.
	 *
	 * @param bool $include_remote Also fetch the service worker over HTTP.
	 * @return array{score:int, band:string, checks:array, deductions:array}
	 */
	public static function run( $include_remote = true ) {
		$checks = array(
			self::check_https(),
			self::check_php(),
			self::check_crypto(),
			self::check_vapid(),
			self::check_transport(),
		);

		if ( $include_remote ) {
			$checks[] = self::check_service_worker();
		}

		$checks[] = self::check_subscribers();
		$checks[] = self::check_legacy();
		$checks[] = self::check_queue();
		$checks[] = self::check_delivery();
		$checks[] = self::check_app();

		/**
		 * Filter the health checks. Add a check by appending an array shaped
		 * like the others; a Pro monitor hangs extra checks off this.
		 *
		 * @param array $checks
		 */
		$checks = (array) \apply_filters( 'upn_health_checks', $checks );

		return self::score( $checks );
	}

	/**
	 * Turn check results into a score with an audit trail.
	 *
	 * @param array $checks
	 * @return array
	 */
	public static function score( array $checks ) {
		$score      = 100;
		$deductions = array();

		foreach ( $checks as $check ) {
			if ( ! isset( $check['id'], $check['status'] ) || self::PASS === $check['status'] ) {
				continue;
			}

			$weight = isset( self::WEIGHTS[ $check['id'] ] )
				? self::WEIGHTS[ $check['id'] ]
				: ( isset( $check['weight'] ) ? (int) $check['weight'] : 10 );

			$deduct = ( self::FAIL === $check['status'] ) ? $weight : (int) \ceil( $weight / 2 );

			$score -= $deduct;
			$deductions[] = array(
				'id'     => $check['id'],
				'status' => $check['status'],
				'points' => $deduct,
			);
		}

		$score = \max( 0, \min( 100, $score ) );

		if ( $score >= 80 ) {
			$band = 'good';
		} elseif ( $score >= 50 ) {
			$band = 'fair';
		} else {
			$band = 'poor';
		}

		return array(
			'score'      => $score,
			'band'       => $band,
			'checks'     => \array_values( $checks ),
			'deductions' => $deductions,
		);
	}

	/* ------------------------------------------------------------------ *
	 * Individual checks. Each returns id, label, status, message, fix.
	 * ------------------------------------------------------------------ */

	public static function check_https() {
		$home   = \home_url( '/' );
		$scheme = \wp_parse_url( $home, PHP_URL_SCHEME );
		$is_ssl = ( 'https' === $scheme ) || \is_ssl();

		return self::result(
			'https',
			\__( 'HTTPS', 'ultimate-push-notifications' ),
			$is_ssl ? self::PASS : self::FAIL,
			$is_ssl
				? \__( 'The site is served over HTTPS.', 'ultimate-push-notifications' )
				: \__( 'The site address is not HTTPS. Browsers refuse to register push subscriptions on insecure origins.', 'ultimate-push-notifications' ),
			$is_ssl ? '' : \__( 'Install an SSL certificate and set the WordPress Address and Site Address to https://.', 'ultimate-push-notifications' )
		);
	}

	public static function check_php() {
		$ok = \version_compare( PHP_VERSION, '7.4', '>=' );

		return self::result(
			'php',
			\__( 'PHP version', 'ultimate-push-notifications' ),
			$ok ? self::PASS : self::FAIL,
			\sprintf(
				/* translators: %s: PHP version */
				\__( 'Running PHP %s.', 'ultimate-push-notifications' ),
				PHP_VERSION
			),
			$ok ? '' : \__( 'Web Push needs PHP 7.4 or newer. Ask your host to upgrade.', 'ultimate-push-notifications' )
		);
	}

	public static function check_crypto() {
		$supported = Ec::keygen_supported();

		return self::result(
			'crypto',
			\__( 'Cryptography', 'ultimate-push-notifications' ),
			true === $supported ? self::PASS : self::FAIL,
			true === $supported
				? \__( 'OpenSSL can derive keys and perform the ECDH exchange every send requires.', 'ultimate-push-notifications' )
				: (string) $supported,
			true === $supported ? '' : \__( 'This is a server configuration problem. Share the message above with your host.', 'ultimate-push-notifications' )
		);
	}

	public static function check_vapid() {
		if ( ! Vapid::has_keys() ) {
			return self::result(
				'vapid',
				\__( 'Web Push keys', 'ultimate-push-notifications' ),
				self::FAIL,
				\__( 'No VAPID key pair has been generated.', 'ultimate-push-notifications' ),
				\__( 'Open App Config and click “Generate key pair”.', 'ultimate-push-notifications' ),
				array( 'action' => 'generate_vapid' )
			);
		}

		$keys    = Vapid::get_keys();
		$derived = Ec::derive_public_point( (string) Ec::b64_decode( $keys['private'] ) );
		$valid   = false !== $derived && \hash_equals( $derived, (string) Ec::b64_decode( $keys['public'] ) );

		return self::result(
			'vapid',
			\__( 'Web Push keys', 'ultimate-push-notifications' ),
			$valid ? self::PASS : self::FAIL,
			$valid
				? \__( 'A valid VAPID key pair is stored.', 'ultimate-push-notifications' )
				: \__( 'The stored VAPID private key does not match the public key. Every push service will reject sends.', 'ultimate-push-notifications' ),
			$valid ? '' : \__( 'Regenerate the key pair on App Config. Devices will need to re-subscribe.', 'ultimate-push-notifications' )
		);
	}

	public static function check_transport() {
		$transport = TransportFactory::preferred();
		$ready     = $transport->is_configured();

		return self::result(
			'transport',
			\__( 'Delivery transport', 'ultimate-push-notifications' ),
			true === $ready ? self::PASS : self::FAIL,
			true === $ready
				? \sprintf(
					/* translators: %s: transport label */
					\__( 'Sending via %s.', 'ultimate-push-notifications' ),
					$transport->get_label()
				)
				: $ready->get_error_message(),
			true === $ready ? '' : \__( 'Set up Web Push on App Config.', 'ultimate-push-notifications' )
		);
	}

	/**
	 * Fetch the worker the way a browser would.
	 *
	 * A loopback request can be blocked by a host, so an unreachable worker is
	 * a warning with the reason, not a failure — but a worker that comes back
	 * as HTML (a security plugin or maintenance page intercepting it) is a
	 * failure, because browsers will refuse to register it.
	 */
	public static function check_service_worker() {
		$url      = Upn_ServiceWorker::url();
		$response = \wp_remote_get(
			$url,
			array(
				'timeout'   => 8,
				'sslverify' => \apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return self::result(
				'service_worker',
				\__( 'Service worker', 'ultimate-push-notifications' ),
				self::WARN,
				\sprintf(
					/* translators: %s: error */
					\__( 'The server could not fetch its own service worker (%s). This is often a host blocking loopback requests and may not affect browsers.', 'ultimate-push-notifications' ),
					$response->get_error_message()
				),
				\sprintf(
					/* translators: %s: URL */
					\__( 'Open %s in a browser — it should show JavaScript, not an error page.', 'ultimate-push-notifications' ),
					$url
				)
			);
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		$type = (string) \wp_remote_retrieve_header( $response, 'content-type' );
		$body = (string) \wp_remote_retrieve_body( $response );

		$is_js    = false !== \stripos( $type, 'javascript' );
		$has_push = false !== \strpos( $body, "addEventListener('push'" );

		if ( 200 === $code && $is_js && $has_push ) {
			return self::result(
				'service_worker',
				\__( 'Service worker', 'ultimate-push-notifications' ),
				self::PASS,
				\__( 'The service worker is served from the site root with the correct content type.', 'ultimate-push-notifications' ),
				''
			);
		}

		return self::result(
			'service_worker',
			\__( 'Service worker', 'ultimate-push-notifications' ),
			self::FAIL,
			\sprintf(
				/* translators: 1: HTTP status, 2: content type */
				\__( 'The service worker URL returned HTTP %1$d with content type “%2$s”. Browsers require 200 and a JavaScript type.', 'ultimate-push-notifications' ),
				$code,
				$type ? $type : \__( '(none)', 'ultimate-push-notifications' )
			),
			\__( 'A caching, security or maintenance-mode plugin is probably intercepting ?upn_sw=1. Exclude it from caching and firewall rules.', 'ultimate-push-notifications' )
		);
	}

	public static function check_subscribers() {
		$total   = SubscriptionStore::count();
		$webpush = SubscriptionStore::count( Subscription::TRANSPORT_WEBPUSH );

		if ( 0 === $total ) {
			return self::result(
				'subscribers',
				\__( 'Registered devices', 'ultimate-push-notifications' ),
				self::WARN,
				\__( 'No devices are registered yet.', 'ultimate-push-notifications' ),
				\__( 'Open “Register My Device” and allow notifications to register this browser.', 'ultimate-push-notifications' )
			);
		}

		return self::result(
			'subscribers',
			\__( 'Registered devices', 'ultimate-push-notifications' ),
			self::PASS,
			\sprintf(
				/* translators: 1: total, 2: web push count */
				\__( '%1$d device(s) registered, %2$d over Web Push.', 'ultimate-push-notifications' ),
				$total,
				$webpush
			),
			''
		);
	}

	public static function check_app() {
		$label = \__( 'Home Screen app', 'ultimate-push-notifications' );
		$s     = \UltimatePushNotifications\pwa\Manifest::settings();
		if ( ! $s['enabled'] ) {
			return self::result( 'app', $label, self::PASS, \__( 'Not enabled. iPhone and iPad visitors can only subscribe once the site is on their Home Screen, which needs a manifest.', 'ultimate-push-notifications' ), \__( 'Turn on “Home Screen app” under Subscribe Prompt.', 'ultimate-push-notifications' ) );
		}
		if ( \UltimatePushNotifications\pwa\Manifest::deferred() ) {
			return self::result( 'app', $label, self::PASS, \__( 'Another plugin provides the manifest; this one stays out of the way.', 'ultimate-push-notifications' ), '' );
		}
		if ( ! \UltimatePushNotifications\pwa\Manifest::icons() ) {
			return self::result( 'app', $label, self::WARN, \__( 'The manifest is served but has no icon, so the site cannot be installed.', 'ultimate-push-notifications' ), \__( 'Set a Site Icon of at least 512×512 under Appearance → Customize → Site Identity.', 'ultimate-push-notifications' ) );
		}
		return self::result( 'app', $label, self::PASS, \sprintf( \__( 'Manifest served at %s with the Site Icon.', 'ultimate-push-notifications' ), \UltimatePushNotifications\pwa\Manifest::url() ), '' );
	}

	public static function check_legacy() {
		$legacy = SubscriptionStore::count( Subscription::TRANSPORT_FCM_LEGACY );

		if ( 0 === $legacy ) {
			return self::result(
				'legacy',
				\__( 'Legacy Firebase devices', 'ultimate-push-notifications' ),
				self::PASS,
				\__( 'No devices remain on the retired Firebase API.', 'ultimate-push-notifications' ),
				''
			);
		}

		return self::result(
			'legacy',
			\__( 'Legacy Firebase devices', 'ultimate-push-notifications' ),
			self::WARN,
			\sprintf(
				/* translators: %d: count */
				\__( '%d device(s) are registered through the legacy Firebase API, which Google shut down on 22 July 2024. They will not receive notifications until they re-register.', 'ultimate-push-notifications' ),
				$legacy
			),
			\__( 'Ask those users to open “Register My Device” once. Their browser will re-register over Web Push automatically.', 'ultimate-push-notifications' ),
			array( 'action' => 'prune_legacy', 'count' => $legacy )
		);
	}

	public static function check_queue() {
		if ( ! Runner::enabled() ) {
			return self::result(
				'queue',
				\__( 'Background queue', 'ultimate-push-notifications' ),
				self::WARN,
				\__( 'The background queue is disabled; notifications are sent inline on the triggering request.', 'ultimate-push-notifications' ),
				\__( 'Fine for small sites. With many devices, checkout and other actions will slow down.', 'ultimate-push-notifications' )
			);
		}

		$driver    = Runner::driver()->name();
		$pending   = JobRepository::pending_count();
		$lag       = JobRepository::oldest_pending_age();
		$last_tick = (int) \get_option( 'upn_queue_last_tick', 0 );
		$cron_off  = \defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && 'cron' === $driver;

		if ( $cron_off ) {
			return self::result(
				'queue',
				\__( 'Background queue', 'ultimate-push-notifications' ),
				self::WARN,
				\__( 'DISABLE_WP_CRON is set and Action Scheduler is not installed, so queued notifications only go out when a real cron job hits wp-cron.php.', 'ultimate-push-notifications' ),
				\__( 'Make sure a system cron runs wp-cron.php every minute, or install a plugin that provides Action Scheduler.', 'ultimate-push-notifications' )
			);
		}

		if ( $pending > 0 && $lag > 600 ) {
			return self::result(
				'queue',
				\__( 'Background queue', 'ultimate-push-notifications' ),
				self::WARN,
				\sprintf(
					/* translators: 1: count, 2: minutes */
					\__( '%1$d notification(s) have been waiting for %2$d minutes. The queue is not draining.', 'ultimate-push-notifications' ),
					$pending,
					(int) \floor( $lag / 60 )
				),
				\__( 'WP-Cron may not be firing. Load any admin page to nudge the queue, and check that cron runs on this host.', 'ultimate-push-notifications' ),
				array( 'action' => 'run_queue' )
			);
		}

		$message = \sprintf(
			/* translators: 1: driver, 2: pending count */
			\__( 'Using %1$s. %2$d job(s) pending.', 'ultimate-push-notifications' ),
			$driver,
			$pending
		);

		if ( $last_tick ) {
			$message .= ' ' . \sprintf(
				/* translators: %s: human time diff */
				\__( 'Last ran %s ago.', 'ultimate-push-notifications' ),
				\human_time_diff( $last_tick, \time() )
			);
		}

		return self::result( 'queue', \__( 'Background queue', 'ultimate-push-notifications' ), self::PASS, $message, '' );
	}

	public static function check_delivery() {
		$stats = DeliveryLog::stats( 7 );

		if ( 0 === $stats['sends'] ) {
			return self::result(
				'delivery',
				\__( 'Recent deliveries', 'ultimate-push-notifications' ),
				self::WARN,
				\__( 'Nothing has been sent in the last 7 days, so there is no delivery evidence yet.', 'ultimate-push-notifications' ),
				\__( 'Send a test notification from “Register My Device” to prove the whole path end to end.', 'ultimate-push-notifications' ),
				array( 'action' => 'send_test' )
			);
		}

		$attempts = $stats['success'] + $stats['fail'];
		$rate     = $attempts > 0 ? $stats['fail'] / $attempts : 0;

		if ( 0 === $stats['success'] ) {
			return self::result(
				'delivery',
				\__( 'Recent deliveries', 'ultimate-push-notifications' ),
				self::FAIL,
				\sprintf(
					/* translators: %d: count */
					\__( '%d notification(s) were sent in the last 7 days and none was delivered.', 'ultimate-push-notifications' ),
					$stats['sends']
				),
				\__( 'Check the other items on this page; the first failing one is usually the cause.', 'ultimate-push-notifications' )
			);
		}

		if ( $attempts >= 5 && $rate >= 0.5 ) {
			return self::result(
				'delivery',
				\__( 'Recent deliveries', 'ultimate-push-notifications' ),
				self::WARN,
				\sprintf(
					/* translators: 1: percent, 2: pruned */
					\__( '%1$d%% of delivery attempts failed this week; %2$d device(s) were removed as expired.', 'ultimate-push-notifications' ),
					(int) \round( $rate * 100 ),
					$stats['pruned']
				),
				\__( 'A high expiry rate after a browser update is normal. A high rate with no expiries points at the VAPID keys or the push service.', 'ultimate-push-notifications' )
			);
		}

		return self::result(
			'delivery',
			\__( 'Recent deliveries', 'ultimate-push-notifications' ),
			self::PASS,
			\sprintf(
				/* translators: 1: sends, 2: delivered, 3: last success */
				\__( '%1$d send(s) this week, %2$d delivered. Last confirmed delivery: %3$s.', 'ultimate-push-notifications' ),
				$stats['sends'],
				$stats['success'],
				$stats['last_success'] ? $stats['last_success'] : \__( 'never', 'ultimate-push-notifications' )
			),
			''
		);
	}

	/**
	 * Shape one result.
	 *
	 * @param string $id
	 * @param string $label
	 * @param string $status
	 * @param string $message
	 * @param string $fix
	 * @param array  $meta
	 * @return array
	 */
	private static function result( $id, $label, $status, $message, $fix = '', array $meta = array() ) {
		return array(
			'id'      => $id,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
			'fix'     => $fix,
			'meta'    => $meta,
		);
	}

}
