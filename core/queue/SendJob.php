<?php namespace UltimatePushNotifications\queue;

use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\transport\DeliveryLog;
use UltimatePushNotifications\transport\Subscription;
use UltimatePushNotifications\transport\SubscriptionStore;

/**
 * The job that delivers one notification to one subscription.
 *
 * One job per recipient, deliberately. A "send to 500 devices" job that dies
 * at device 300 would either restart from zero (300 duplicates) or need its
 * own cursor. One-per-recipient makes the queue's own retry the retry, and a
 * push service's Retry-After becomes the job's delay.
 *
 * The payload carries ids and the already-built notification fields, never
 * anything that needs re-resolving: by the time the job runs, the order that
 * triggered it may have changed state again.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class SendJob {

	const TYPE = 'upn_send';

	/**
	 * Register the handler.
	 *
	 * @return void
	 */
	public static function boot() {
		\add_filter( 'upn_queue_job_handlers', array( __CLASS__, 'register' ) );
	}

	/**
	 * @param array $handlers
	 * @return array
	 */
	public static function register( $handlers ) {
		$handlers[ self::TYPE ] = array( __CLASS__, 'handle' );
		return $handlers;
	}

	/**
	 * Queue one delivery.
	 *
	 * @param int   $subscription_id
	 * @param array $payload  Built notification fields.
	 * @param array $options  Send options (type, ttl, urgency, topic).
	 * @param int   $log_id   Delivery log row to roll the result into.
	 * @return array JobRepository::enqueue() result.
	 */
	public static function enqueue( $subscription_id, array $payload, array $options, $log_id ) {
		$job_payload = array(
			'subscription_id' => (int) $subscription_id,
			'payload'         => $payload,
			'options'         => $options,
			'log_id'          => (int) $log_id,
		);

		/*
		 * The default key would collapse two identical notifications to the
		 * same device into one — fine for content, wrong for "your order
		 * status changed" twice in a minute. The log id is unique per send, so
		 * including it keeps the dedupe to genuine duplicates.
		 */
		return JobRepository::enqueue(
			self::TYPE,
			$job_payload,
			array( 'job_key' => \sha1( self::TYPE . '|' . $log_id . '|' . (int) $subscription_id ) )
		);
	}

	/**
	 * Run one delivery.
	 *
	 * @param array  $payload Job payload.
	 * @param object $job     Job row.
	 * @return JobOutcome|null
	 */
	public static function handle( array $payload, $job = null ) {
		$subscription_id = isset( $payload['subscription_id'] ) ? (int) $payload['subscription_id'] : 0;
		$log_id          = isset( $payload['log_id'] ) ? (int) $payload['log_id'] : 0;
		$fields          = isset( $payload['payload'] ) && \is_array( $payload['payload'] ) ? $payload['payload'] : array();
		$options         = isset( $payload['options'] ) && \is_array( $payload['options'] ) ? $payload['options'] : array();

		$subscription = SubscriptionStore::get( $subscription_id );

		if ( ! $subscription ) {
			// Unsubscribed between enqueue and run. Not a failure — nothing to do.
			return JobOutcome::done();
		}

		if ( $log_id > 0 ) {
			$options['log_id'] = $log_id;
		}

		$gate = SendNotifications::gate( $subscription, $fields, $options );
		if ( '' !== $gate['skip'] ) {
			if ( $log_id > 0 ) {
				DeliveryLog::apply_skip( $log_id );
			}
			/**
			 * A delivery was dropped before it was attempted.
			 *
			 * @param string       $reason
			 * @param Subscription $subscription
			 * @param array        $fields
			 * @param array        $options
			 */
			\do_action( 'upn_delivery_skipped', $gate['skip'], $subscription, $fields, $options );
			return JobOutcome::done();
		}
		if ( $gate['hold'] > 0 ) {
			return JobOutcome::defer( 'held', $gate['hold'] );
		}

		$result = SendNotifications::deliver( $subscription, $fields, $options );

		if ( $log_id > 0 ) {
			DeliveryLog::apply_result( $log_id, $result );
		}

		if ( $result->retryable ) {
			return JobOutcome::retry(
				$result->summary(),
				null !== $result->retry_after ? (int) $result->retry_after : 0
			);
		}

		return JobOutcome::done();
	}

}
