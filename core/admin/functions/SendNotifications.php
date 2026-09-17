<?php namespace UltimatePushNotifications\admin\functions;

/**
 * Send notifications
 *
 * The single entry point every event handler calls. It knows nothing about
 * Firebase or Web Push: it builds a payload, hands each subscription to the
 * transport layer, and records what came back.
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\queue\Runner;
use UltimatePushNotifications\queue\SendJob;
use UltimatePushNotifications\transport\DeliveryLog;
use UltimatePushNotifications\transport\SendResult;
use UltimatePushNotifications\transport\Subscription;
use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\TransportFactory;

class SendNotifications {

	/**
	 * Send Test Notifications
	 *
	 * Accepts either a subscription row id (preferred) or, for the existing
	 * device-list markup, a raw legacy token. The response carries the real
	 * reason on failure, because "check your configuration" was the message
	 * that sat on this plugin's support forum unanswered.
	 *
	 * @param array $user_input
	 * @return void
	 */
	public function send_test_notifications( $user_input ) {

		$subscription = null;

		if ( ! empty( $user_input['device_id'] ) ) {
			$subscription = SubscriptionStore::get( (int) $user_input['device_id'] );
		} elseif ( ! empty( $user_input['device_token'] ) ) {
			$subscription = SubscriptionStore::find_by_token( Util::check_evil_script( $user_input['device_token'] ) );
		}

		if ( ! $subscription ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error!', 'ultimate-push-notifications' ),
					'text'   => __( 'That device is no longer registered.', 'ultimate-push-notifications' ),
				)
			);
		}

		/*
		 * A test send is the one place a user may legitimately target a device
		 * that is not their own — an administrator checking a subscriber's row.
		 * Anyone else may only test their own devices.
		 */
		if ( (int) $subscription->user_id !== \get_current_user_id() && ! \current_user_can( 'manage_options' ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'ultimate-push-notifications' ),
					'text'   => __( 'You can only send a test notification to your own devices.', 'ultimate-push-notifications' ),
				)
			);
		}

		$current_user = \wp_get_current_user();

		$payload = self::build_payload(
			array(
				'title'        => __( 'Ultimate Push Notification', 'ultimate-push-notifications' ),
				'body'         => sprintf(
					/* translators: %s: user login */
					__( "Hi %s, I'm Ultimate Push Notifications. Hope you will enjoy it!", 'ultimate-push-notifications' ),
					$current_user->user_login
				),
				'icon'         => CS_UPN_PLUGIN_ASSET_URI . 'img/icon-push.png',
				'click_action' => site_url(),
			)
		);

		$result = self::deliver( $subscription, $payload, array( 'type' => 'test' ) );
		DeliveryLog::record( 'test', $payload, array( $result ) );

		if ( $result->success ) {
			return wp_send_json(
				array(
					'status' => true,
					'title'  => __( 'Success!', 'ultimate-push-notifications' ),
					'text'   => __( 'Notification sent successfully.', 'ultimate-push-notifications' ),
				)
			);
		}

		$text = $result->error_message;
		if ( $result->subscription_gone ) {
			$text .= ' ' . __( 'The device has been removed from the list.', 'ultimate-push-notifications' );
		}

		return wp_send_json(
			array(
				'status' => false,
				'title'  => __( 'Failure!', 'ultimate-push-notifications' ),
				'text'   => $text,
			)
		);
	}

	/**
	 * Prepare and send notifications to every subscription in $dataObj->tokens.
	 *
	 * The contract the event handlers rely on: title and body have their
	 * placeholders substituted, tokens is a list of device rows, and the return
	 * value is one entry per delivery attempt.
	 *
	 * @param array|object $dataObj title, body, icon, image, click_action, find, replace, tokens, type
	 * @return SendResult[]
	 */
	public static function prepare_send_notifications( $dataObj ) {
		$dataObj = \is_object( $dataObj ) ? $dataObj : (object) $dataObj;

		if ( empty( $dataObj->tokens ) ) {
			return array();
		}

		$find    = isset( $dataObj->find ) ? (array) $dataObj->find : array();
		$replace = isset( $dataObj->replace ) ? (array) $dataObj->replace : array();

		$payload = self::build_payload(
			array(
				'title'        => \str_replace( $find, $replace, isset( $dataObj->title ) ? $dataObj->title : '' ),
				'body'         => \str_replace( $find, $replace, isset( $dataObj->body ) ? $dataObj->body : '' ),
				'icon'         => isset( $dataObj->icon ) ? $dataObj->icon : '',
				'image'        => isset( $dataObj->image ) ? $dataObj->image : '',
				'click_action' => isset( $dataObj->click_action ) ? $dataObj->click_action : site_url(),
			)
		);

		$options = array(
			'type' => isset( $dataObj->type ) ? (string) $dataObj->type : 'event',
		);

		$subscriptions = SubscriptionStore::hydrate( $dataObj->tokens );

		if ( ! $subscriptions ) {
			return array();
		}

		/*
		 * Queue by default. The triggering request — a customer's checkout, a
		 * member sending a message — returns immediately, and delivery happens
		 * in a background tick with retries and per-service backoff. The
		 * inline path remains for sites where no scheduler is available.
		 */
		if ( Runner::enabled() ) {
			return self::enqueue_all( $subscriptions, $payload, $options );
		}

		$results = array();

		foreach ( $subscriptions as $subscription ) {
			// Inline there is nothing to wait with, so a hold cannot be honoured; a skip can.
			if ( '' !== self::gate( $subscription, $payload, $options )['skip'] ) {
				continue;
			}
			$results[] = self::deliver( $subscription, $payload, $options );
		}

		DeliveryLog::record( $options['type'], $payload, $results );

		return $results;
	}

	/**
	 * Queue one job per subscription and kick the runner.
	 *
	 * Returns one placeholder result per recipient so callers that count the
	 * return value still get the right number. The real outcomes land in the
	 * delivery log as the jobs complete.
	 *
	 * @param Subscription[] $subscriptions
	 * @param array          $payload
	 * @param array          $options
	 * @return SendResult[]
	 */
	private static function enqueue_all( array $subscriptions, array $payload, array $options ) {
		$log_id  = DeliveryLog::open( $options['type'], $payload, \count( $subscriptions ) );
		$results = array();

		foreach ( $subscriptions as $subscription ) {
			SendJob::enqueue( $subscription->id, $payload, $options, (int) $log_id );

			$queued            = SendResult::success( 0, $subscription->transport );
			$queued->error_code = 'queued';
			$results[]         = $queued;
		}

		Runner::schedule( 0 );

		return $results;
	}

	/**
	 * Should this delivery wait, or not happen at all?
	 *
	 * Asked once per recipient just before delivery — the only point where the
	 * subscriber, the notification and the moment are all known. Quiet hours
	 * and frequency caps hang off this. Test and preview sends are never
	 * gated: a send the owner is watching for must arrive.
	 *
	 * @param Subscription $subscription
	 * @param array        $payload
	 * @param array        $options
	 * @return array{hold:int,skip:string} hold = seconds to wait (0 = none); skip = reason ("" = deliver).
	 */
	public static function gate( Subscription $subscription, array $payload, array $options ) {
		$none = array( 'hold' => 0, 'skip' => '' );
		$type = isset( $options['type'] ) ? (string) $options['type'] : '';
		if ( 'test' === $type || 'preview' === $type ) {
			return $none;
		}

		/**
		 * Return a non-empty reason to drop this delivery for this subscriber.
		 *
		 * @param string       $reason
		 * @param Subscription $subscription
		 * @param array        $payload
		 * @param array        $options
		 */
		$skip = \apply_filters( 'upn_delivery_skip', '', $subscription, $payload, $options );
		if ( \is_string( $skip ) && '' !== $skip ) {
			return array( 'hold' => 0, 'skip' => $skip );
		}

		/**
		 * Return seconds to wait before delivering to this subscriber (0 = now).
		 * Only the queued path can wait; inline sends ignore this.
		 *
		 * @param int          $seconds
		 * @param Subscription $subscription
		 * @param array        $payload
		 * @param array        $options
		 */
		$hold = (int) \apply_filters( 'upn_delivery_hold', 0, $subscription, $payload, $options );

		return array( 'hold' => \max( 0, $hold ), 'skip' => '' );
	}

	/**
	 * Deliver one notification and record the outcome.
	 *
	 * @param Subscription $subscription
	 * @param array        $payload
	 * @param array        $options
	 * @return SendResult
	 */
	public static function deliver( Subscription $subscription, array $payload, array $options = array() ) {

		/**
		 * Filter the payload immediately before it is handed to a transport.
		 *
		 * @param array        $payload
		 * @param Subscription $subscription
		 * @param array        $options
		 */
		$payload = (array) \apply_filters( 'upn_before_send', $payload, $subscription, $options );

		// The service worker reports clicks against this id.
		if ( ! empty( $options['log_id'] ) ) {
			$payload['log_id'] = (int) $options['log_id'];
		}

		$result = TransportFactory::send( $subscription, $payload, $options );

		SubscriptionStore::record_result( $subscription, $result );

		/**
		 * Fires after every delivery attempt, successful or not.
		 *
		 * The delivery log and the health monitor hang off this.
		 *
		 * @param SendResult   $result
		 * @param Subscription $subscription
		 * @param array        $payload
		 * @param array        $options
		 */
		\do_action( 'upn_after_send', $result, $subscription, $payload, $options );

		return $result;
	}

	/**
	 * Normalise the fields every transport understands.
	 *
	 * Drops empty optional fields so the encrypted payload stays small — the
	 * push services cap it around 4KB, and an empty "image": "" is wasted room.
	 *
	 * @param array $fields
	 * @return array
	 */
	public static function build_payload( array $fields ) {
		$payload = array(
			'title'        => isset( $fields['title'] ) ? \wp_strip_all_tags( (string) $fields['title'] ) : '',
			'body'         => isset( $fields['body'] ) ? \wp_strip_all_tags( (string) $fields['body'] ) : '',
			'click_action' => isset( $fields['click_action'] ) && '' !== $fields['click_action']
				? \esc_url_raw( $fields['click_action'] )
				: site_url(),
		);

		foreach ( array( 'icon', 'image', 'badge', 'tag' ) as $optional ) {
			if ( ! empty( $fields[ $optional ] ) ) {
				$payload[ $optional ] = ( 'tag' === $optional )
					? \sanitize_key( $fields[ $optional ] )
					: \esc_url_raw( $fields[ $optional ] );
			}
		}

		if ( ! empty( $fields['actions'] ) && \is_array( $fields['actions'] ) ) {
			$payload['actions'] = array();
			foreach ( \array_slice( \array_values( $fields['actions'] ), 0, 2 ) as $i => $a ) {
				if ( empty( $a['title'] ) || empty( $a['url'] ) ) {
					continue;
				}
				$payload['actions'][] = array( 'action' => 'a' . $i, 'title' => \wp_strip_all_tags( (string) $a['title'] ), 'url' => \esc_url_raw( (string) $a['url'] ) );
			}
			if ( ! $payload['actions'] ) {
				unset( $payload['actions'] );
			}
		}

		return $payload;
	}

}
