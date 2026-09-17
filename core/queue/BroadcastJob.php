<?php namespace UltimatePushNotifications\queue;

use UltimatePushNotifications\transport\SubscriptionStore;

/**
 * Fans a broadcast out into one SendJob per recipient, a page at a time.
 *
 * The composer enqueues exactly one of these and returns. Each run takes the
 * next page of the audience, queues a SendJob for every subscription in it,
 * and re-enqueues itself with the offset advanced — the cursor pattern from
 * the sibling product. A 50,000-subscriber broadcast is therefore one queue
 * row at a time on the admin's request and a few hundred inserts per tick,
 * rather than 50,000 inserts inside a page load.
 *
 * The audience is re-queried on each page rather than snapshotted: anyone who
 * unsubscribes mid-broadcast is simply not there when their page comes up.
 *
 * @package Queue
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class BroadcastJob {

	const TYPE = 'upn_broadcast';

	/** Subscriptions fanned out per run. */
	const PAGE = 200;

	public static function boot() {
		\add_filter( 'upn_queue_job_handlers', array( __CLASS__, 'register' ) );
	}

	public static function register( $handlers ) {
		$handlers[ self::TYPE ] = array( __CLASS__, 'handle' );
		return $handlers;
	}

	/**
	 * Queue the first page of a broadcast.
	 *
	 * @param int   $log_id
	 * @param array $audience See SubscriptionStore::audience_where().
	 * @param array $payload  Built notification fields, already rendered.
	 * @param array $options  Send options.
	 * @return array JobRepository::enqueue() result.
	 */
	public static function enqueue( $log_id, array $audience, array $payload, array $options ) {
		return self::enqueue_page( (int) $log_id, $audience, $payload, $options, 0 );
	}

	/**
	 * @param array  $payload Job payload.
	 * @param object $job
	 * @return JobOutcome
	 */
	public static function handle( array $payload, $job = null ) {
		$log_id   = isset( $payload['log_id'] ) ? (int) $payload['log_id'] : 0;
		$audience = isset( $payload['audience'] ) && \is_array( $payload['audience'] ) ? $payload['audience'] : array();
		$fields   = isset( $payload['payload'] ) && \is_array( $payload['payload'] ) ? $payload['payload'] : array();
		$options  = isset( $payload['options'] ) && \is_array( $payload['options'] ) ? $payload['options'] : array();
		$offset   = isset( $payload['offset'] ) ? \max( 0, (int) $payload['offset'] ) : 0;

		$page = SubscriptionStore::query_audience( $audience, self::PAGE, $offset );

		foreach ( $page as $subscription ) {
			SendJob::enqueue( $subscription->id, $fields, $options, $log_id );
		}

		if ( \count( $page ) === self::PAGE ) {
			self::enqueue_page( $log_id, $audience, $fields, $options, $offset + self::PAGE );
		}

		/**
		 * Fires after a broadcast page has been fanned out.
		 *
		 * @param int $log_id
		 * @param int $offset
		 * @param int $count
		 */
		\do_action( 'upn_broadcast_page_queued', $log_id, $offset, \count( $page ) );

		return JobOutcome::done();
	}

	private static function enqueue_page( $log_id, array $audience, array $payload, array $options, $offset ) {
		return JobRepository::enqueue(
			self::TYPE,
			array(
				'log_id'   => $log_id,
				'audience' => $audience,
				'payload'  => $payload,
				'options'  => $options,
				'offset'   => $offset,
			),
			// One row per (broadcast, page): re-enqueuing the same page twice is a no-op.
			array( 'job_key' => \sha1( self::TYPE . '|' . $log_id . '|' . $offset ) )
		);
	}

}
