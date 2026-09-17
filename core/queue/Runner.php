<?php namespace UltimatePushNotifications\queue;

/**
 * Drains the queue, a bounded batch at a time.
 *
 * Two budgets, whichever runs out first: a job count and a wall-clock limit.
 * The clock matters more — a tick that hits max_execution_time takes its
 * claimed jobs down with it, and the whole point of claiming is that those
 * jobs come back. Stopping early and rescheduling costs one extra request;
 * being killed costs ten minutes of stale claims.
 *
 * A handler is registered per job type through the upn_queue_job_handlers
 * filter. Anything a handler throws is caught here: one bad job must not take
 * the batch, and it must not take the queue.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Runner {

	/** Jobs per tick, before filtering. */
	const DEFAULT_BATCH = 25;

	/** Seconds of wall clock per tick, before filtering. */
	const DEFAULT_TIME_BUDGET = 15;

	/**
	 * Guards against a tick re-entering itself within one request.
	 *
	 * @var bool
	 */
	private static $running = false;

	/**
	 * Memoised driver.
	 *
	 * @var QueueDriverInterface|null
	 */
	private static $driver = null;

	/**
	 * Attach the tick to its hook. Called once at plugin load.
	 *
	 * @return void
	 */
	public static function boot() {
		\add_action( CronDriver::HOOK, array( __CLASS__, 'tick' ) );
	}

	/**
	 * Is the queue in use on this site?
	 *
	 * On by default whenever a driver exists. A site can force inline sending
	 * — useful for debugging, or where cron is known to be broken — through
	 * the filter or a constant.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( \defined( 'UPN_QUEUE_DISABLED' ) && UPN_QUEUE_DISABLED ) {
			return false;
		}

		/*
		 * No table, no queue. Sends fall back to the inline path rather than
		 * erroring on every request. The installer re-creates a missing table
		 * on the next plugins_loaded, so this is self-healing.
		 */
		if ( ! JobRepository::installed() ) {
			return false;
		}

		$enabled = self::driver()->is_available();

		/**
		 * Filter whether notification sends go through the background queue.
		 *
		 * @param bool $enabled
		 */
		return (bool) \apply_filters( 'upn_queue_enabled', $enabled );
	}

	/**
	 * Run one tick.
	 *
	 * @return array processed, failed, remaining, rescheduled
	 */
	public static function tick() {
		$result = array(
			'processed'   => 0,
			'failed'      => 0,
			'deferred'    => 0,
			'remaining'   => 0,
			'rescheduled' => false,
		);

		if ( self::$running || ! JobRepository::installed() ) {
			return $result;
		}

		self::$running = true;

		try {
			JobRepository::reclaim_stale();

			$batch    = self::batch_size();
			$budget   = self::time_budget();
			$start    = \microtime( true );
			$handlers = self::handlers();
			$jobs     = JobRepository::claim( $batch );

			foreach ( $jobs as $job ) {
				if ( ( \microtime( true ) - $start ) >= $budget ) {
					// Out of time. release(), not fail(): this job has not been
					// attempted, and burning a retry on it would eventually park
					// work that never ran.
					JobRepository::release( $job );
					continue;
				}

				$type = isset( $job->job_type ) ? (string) $job->job_type : '';

				if ( '' === $type || ! isset( $handlers[ $type ] ) || ! \is_callable( $handlers[ $type ] ) ) {
					JobRepository::fail( $job, 'no_handler' );
					++$result['failed'];
					continue;
				}

				$payload = array();
				if ( isset( $job->payload ) && '' !== $job->payload ) {
					$decoded = \json_decode( (string) $job->payload, true );
					$payload = \is_array( $decoded ) ? $decoded : array();
				}

				try {
					$outcome = \call_user_func( $handlers[ $type ], $payload, $job );

					/*
					 * A handler may return a JobOutcome asking for a retry with a
					 * specific delay — that is how a 429 Retry-After from a push
					 * service reaches the queue without being thrown as an error.
					 */
					if ( $outcome instanceof JobOutcome && $outcome->defer ) {
						// Waiting, not failing: no attempt is spent.
						JobRepository::defer( $job, $outcome->delay, $outcome->reason );
						++$result['deferred'];
					} elseif ( $outcome instanceof JobOutcome && $outcome->retry ) {
						JobRepository::fail( $job, $outcome->reason, $outcome->delay );
						++$result['failed'];
					} else {
						JobRepository::complete( $job->id );
						++$result['processed'];
					}
				} catch ( \Throwable $e ) {
					JobRepository::fail( $job, $e->getMessage() );
					++$result['failed'];

					/**
					 * Fires when a job handler throws.
					 *
					 * @param object     $job
					 * @param \Throwable $e
					 */
					\do_action( 'upn_queue_job_failed', $job, $e );
				}
			}

			$result['remaining'] = JobRepository::pending_count();

			if ( $result['remaining'] > 0 ) {
				$result['rescheduled'] = self::driver()->schedule( self::next_delay() );
			} else {
				// Nothing due, but something may be booked for later: a backed-off
				// retry, a scheduled send. Come back when it is due.
				$wait = JobRepository::seconds_until_next();
				if ( null !== $wait ) {
					$result['rescheduled'] = self::driver()->schedule( \max( $wait, self::next_delay() ) );
				}
			}

			\update_option( 'upn_queue_last_tick', \time(), false );
		} catch ( \Throwable $e ) {
			// Never let a queue tick surface as a fatal on somebody's admin
			// page. The jobs are claimed, so they come back.
			\do_action( 'upn_queue_tick_failed', $e );
		}

		self::$running = false;

		return $result;
	}

	/**
	 * Ask for a tick.
	 *
	 * @param int $delay Seconds.
	 * @return bool
	 */
	public static function schedule( $delay = 0 ) {
		return self::driver()->schedule( $delay );
	}

	/**
	 * The driver this site will use: Action Scheduler when present, WP-Cron otherwise.
	 *
	 * @return QueueDriverInterface
	 */
	public static function driver() {
		if ( null !== self::$driver ) {
			return self::$driver;
		}

		$action_scheduler = new ActionSchedulerDriver();
		$driver           = $action_scheduler->is_available() ? $action_scheduler : new CronDriver();

		/**
		 * Filter the queue driver.
		 *
		 * @param QueueDriverInterface $driver
		 */
		$filtered = \apply_filters( 'upn_queue_driver', $driver );

		if ( $filtered instanceof QueueDriverInterface && $filtered->is_available() ) {
			$driver = $filtered;
		}

		self::$driver = $driver;

		return $driver;
	}

	/**
	 * Reset the memoised driver. Tests only.
	 *
	 * @return void
	 */
	public static function reset_driver() {
		self::$driver  = null;
		self::$running = false;
	}

	/**
	 * Job type => callable( array $payload, object $job ).
	 *
	 * @return array
	 */
	public static function handlers() {
		/**
		 * Register queue job handlers.
		 *
		 * @param array $handlers
		 */
		$handlers = \apply_filters( 'upn_queue_job_handlers', array() );

		return \is_array( $handlers ) ? $handlers : array();
	}

	private static function batch_size() {
		/**
		 * Filter how many jobs one tick claims.
		 *
		 * @param int $size
		 */
		$size = (int) \apply_filters( 'upn_queue_batch', self::DEFAULT_BATCH );

		return \max( 1, \min( 50, $size ) );
	}

	/**
	 * Wall-clock seconds per tick, capped under the host's own limit.
	 *
	 * @return int
	 */
	private static function time_budget() {
		/**
		 * Filter the per-tick time budget in seconds.
		 *
		 * @param int $budget
		 */
		$budget = (int) \apply_filters( 'upn_queue_time_budget', self::DEFAULT_TIME_BUDGET );
		$budget = \max( 5, \min( 120, $budget ) );

		$limit = (int) \ini_get( 'max_execution_time' );
		if ( $limit > 0 && $budget > ( $limit - 5 ) ) {
			$budget = \max( 5, $limit - 5 );
		}

		return $budget;
	}

	/**
	 * Gap before the next tick. WP-Cron will not run two single events for the
	 * same hook in the same second, so a small gap keeps the chain moving.
	 *
	 * @return int
	 */
	private static function next_delay() {
		/**
		 * Filter the gap between queue ticks, in seconds.
		 *
		 * @param int $delay
		 */
		$delay = (int) \apply_filters( 'upn_queue_next_delay', 1 );

		return \max( 0, \min( 300, $delay ) );
	}

}
