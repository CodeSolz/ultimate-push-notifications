<?php namespace UltimatePushNotifications\queue;

/**
 * The job table.
 *
 * Claiming is the whole design. A worker does not read a job and then mark it
 * taken — it writes the claim first, conditionally, and only works on rows the
 * database confirms it won:
 *
 *     UPDATE … SET claim_token = %s WHERE status = 'pending' AND claim_token IS NULL
 *
 * Two workers running at the same instant both issue that statement; MySQL
 * serialises them, and the loser updates nothing. A transient-based lock cannot
 * make that promise: transients race, and on a site with a persistent object
 * cache they can be visible to one process and not another.
 *
 * Completed jobs are deleted rather than marked done. job_key is UNIQUE, so a
 * finished job that lingered would permanently block the same work from ever
 * being queued again.
 *
 * Ported from the sibling Better Find and Replace queue, which has run in
 * production since its 2.0.0 release.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

// Table names here are $wpdb->prefix plus a literal; every VALUE travels as a
// placeholder through $wpdb->prepare().
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class JobRepository {

	const STATUS_PENDING = 'pending';
	const STATUS_CLAIMED = 'claimed';
	const STATUS_FAILED  = 'failed';

	/** Attempts before a job is parked as failed. */
	const MAX_ATTEMPTS = 3;

	/** Minutes after which a claim is presumed abandoned. */
	const STALE_MINUTES = 10;

	/**
	 * Fully-prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'upn_jobs';
	}

	/**
	 * Does the jobs table exist?
	 *
	 * Memoised per request. Every public entry point that would otherwise
	 * issue a query against a missing table goes through Runner::enabled(),
	 * which checks this first.
	 *
	 * @return bool
	 */
	public static function installed() {
		static $installed = null;

		if ( null !== $installed ) {
			return $installed;
		}

		global $wpdb;

		$table     = self::table();
		$found     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$installed = ( $found === $table );

		return $installed;
	}

	/**
	 * The deterministic identity of a job.
	 *
	 * job_key is UNIQUE, so a pending job with the same key stops a retried
	 * enqueue creating a second copy. Callers that want the same logical work
	 * queued again later must include something that varies.
	 *
	 * @param string $type
	 * @param array  $payload
	 * @return string 40-char hex.
	 */
	public static function key( $type, array $payload = array() ) {
		\ksort( $payload );
		return \sha1( (string) $type . '|' . \wp_json_encode( $payload ) );
	}

	/**
	 * Add a job unless an identical one is already waiting.
	 *
	 * @param string $type
	 * @param array  $payload Small: ids and options, never content.
	 * @param array  $args    delay (seconds), job_key (explicit key).
	 * @return array array( 'queued' => bool, 'id' => int, 'reason' => string )
	 */
	public static function enqueue( $type, array $payload = array(), array $args = array() ) {
		global $wpdb;

		$type = (string) $type;

		if ( '' === $type ) {
			return array( 'queued' => false, 'id' => 0, 'reason' => 'missing_type' );
		}

		$job_key = isset( $args['job_key'] ) && '' !== $args['job_key']
			? (string) $args['job_key']
			: self::key( $type, $payload );

		$delay = isset( $args['delay'] ) ? \max( 0, (int) $args['delay'] ) : 0;
		$now   = self::now();

		// Hitting the unique index is an expected outcome, not an error worth logging.
		$suppress = $wpdb->suppress_errors( true );

		$ok = $wpdb->insert(
			self::table(),
			array(
				'job_key'      => $job_key,
				'job_type'     => $type,
				'payload'      => \wp_json_encode( $payload ),
				'status'       => self::STATUS_PENDING,
				'attempts'     => 0,
				'claim_token'  => null,
				'available_at' => $delay > 0 ? self::in_seconds( $delay ) : $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		$wpdb->suppress_errors( $suppress );

		if ( ! $ok ) {
			return array( 'queued' => false, 'id' => 0, 'reason' => 'duplicate' );
		}

		return array( 'queued' => true, 'id' => (int) $wpdb->insert_id, 'reason' => '' );
	}

	/**
	 * Take ownership of up to $limit due jobs.
	 *
	 * The claim is written before anything is read back, so the rows returned
	 * are provably ours.
	 *
	 * @param int $limit
	 * @return object[] Possibly empty.
	 */
	public static function claim( $limit = 5 ) {
		global $wpdb;

		$limit = \max( 1, \min( 50, (int) $limit ) );
		$token = self::token();
		$table = self::table();
		$now   = self::now();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET claim_token = %s, claimed_at = %s, status = %s, attempts = attempts + 1, updated_at = %s
				 WHERE status = %s AND claim_token IS NULL AND available_at <= %s
				 ORDER BY id ASC
				 LIMIT %d",
				array( $token, $now, self::STATUS_CLAIMED, $now, self::STATUS_PENDING, $now, $limit )
			)
		);

		if ( ( (int) $wpdb->rows_affected ) < 1 ) {
			return array();
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE claim_token = %s ORDER BY id ASC", $token )
		);
	}

	/**
	 * The job is done. Remove it.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function complete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Hand a claimed job back untouched.
	 *
	 * For a job that was claimed but never started — the tick ran out of time
	 * before reaching it. The attempt counter is wound back, because a job
	 * that did not run has not failed, and three unlucky ticks must not park
	 * work that was never attempted.
	 *
	 * @param object $job
	 * @return bool
	 */
	public static function release( $job ) {
		global $wpdb;

		$id = isset( $job->id ) ? (int) $job->id : 0;
		if ( $id <= 0 ) {
			return false;
		}

		$table = self::table();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET status = %s, claim_token = NULL, claimed_at = NULL,
				     attempts = GREATEST(0, attempts - 1), updated_at = %s
				 WHERE id = %d",
				array( self::STATUS_PENDING, self::now(), $id )
			)
		);

		return ( (int) $wpdb->rows_affected ) > 0;
	}

	/**
	 * Put a claimed job back for a specific moment without spending an
	 * attempt — the handler chose to wait, nothing went wrong.
	 *
	 * @param object $job
	 * @param int    $delay  Seconds from now.
	 * @param string $reason Kept in last_error so the wait is visible.
	 * @return bool
	 */
	public static function defer( $job, $delay, $reason = '' ) {
		global $wpdb;

		$id = isset( $job->id ) ? (int) $job->id : 0;
		if ( $id <= 0 ) {
			return false;
		}

		$table = self::table();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET status = %s, claim_token = NULL, claimed_at = NULL,
				     attempts = GREATEST(0, attempts - 1), available_at = %s, last_error = %s, updated_at = %s
				 WHERE id = %d",
				array( self::STATUS_PENDING, self::in_seconds( \max( 0, (int) $delay ) ), \substr( (string) $reason, 0, 255 ), self::now(), $id )
			)
		);

		return ( (int) $wpdb->rows_affected ) > 0;
	}

	/**
	 * The job failed. Put it back with backoff, or park it.
	 *
	 * @param object $job
	 * @param string $error
	 * @param int    $retry_after Seconds the caller wants to wait, when known
	 *                            (a push service's Retry-After). Overrides the
	 *                            exponential backoff when larger.
	 * @return void
	 */
	public static function fail( $job, $error = '', $retry_after = 0 ) {
		global $wpdb;

		$id       = isset( $job->id ) ? (int) $job->id : 0;
		$attempts = isset( $job->attempts ) ? (int) $job->attempts : 1;

		if ( $id <= 0 ) {
			return;
		}

		$error = \substr( (string) $error, 0, 255 );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$wpdb->update(
				self::table(),
				array(
					'status'      => self::STATUS_FAILED,
					'claim_token' => null,
					'last_error'  => $error,
					'updated_at'  => self::now(),
				),
				array( 'id' => $id )
			);

			/**
			 * Fires when a job has exhausted its attempts.
			 *
			 * @param object $job
			 * @param string $error
			 */
			\do_action( 'upn_queue_job_parked', $job, $error );

			return;
		}

		// Exponential in the attempt count, so a push service that is down is
		// not hammered by a queue that keeps waking up.
		$backoff = (int) \min( 3600, 30 * \pow( 2, \max( 0, $attempts - 1 ) ) );
		$backoff = \max( $backoff, (int) $retry_after );

		$wpdb->update(
			self::table(),
			array(
				'status'       => self::STATUS_PENDING,
				'claim_token'  => null,
				'claimed_at'   => null,
				'available_at' => self::in_seconds( $backoff ),
				'last_error'   => $error,
				'updated_at'   => self::now(),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Return claims whose worker never came back.
	 *
	 * A PHP process killed mid-job leaves its rows claimed forever otherwise.
	 * attempts is not reset, so a job that reliably kills its worker still
	 * gets parked rather than looping.
	 *
	 * @param int $minutes
	 * @return int Jobs released.
	 */
	public static function reclaim_stale( $minutes = self::STALE_MINUTES ) {
		global $wpdb;

		$table  = self::table();
		$cutoff = self::ago( \max( 1, (int) $minutes ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET status = %s, claim_token = NULL, claimed_at = NULL, updated_at = %s
				 WHERE status = %s AND claimed_at IS NOT NULL AND claimed_at < %s",
				array( self::STATUS_PENDING, self::now(), self::STATUS_CLAIMED, $cutoff )
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Jobs waiting to run right now.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;

		$table = self::table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE status = %s AND available_at <= %s",
				array( self::STATUS_PENDING, self::now() )
			)
		);
	}

	/**
	 * Counts per status, for diagnostics.
	 *
	 * @return array status => count
	 */
	public static function counts() {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM `{$table}` GROUP BY status" );
		$out   = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row->status ] = (int) $row->total;
		}

		return $out;
	}

	/**
	 * Remove parked jobs so a fixed problem can be re-queued.
	 *
	 * @param string $type Optional job type.
	 * @return int
	 */
	public static function purge_failed( $type = '' ) {
		global $wpdb;

		$table = self::table();
		$type  = (string) $type;

		if ( '' === $type ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE status = %s", self::STATUS_FAILED ) );
		} else {
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM `{$table}` WHERE status = %s AND job_type = %s", array( self::STATUS_FAILED, $type ) )
			);
		}

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Oldest pending job's age in seconds — the queue's lag.
	 *
	 * @return int 0 when empty.
	 */
	public static function oldest_pending_age() {
		global $wpdb;

		$table  = self::table();
		$oldest = $wpdb->get_var(
			$wpdb->prepare( "SELECT MIN(available_at) FROM `{$table}` WHERE status = %s", self::STATUS_PENDING )
		);

		if ( ! $oldest ) {
			return 0;
		}

		return \max( 0, \time() - (int) \strtotime( $oldest . ' UTC' ) );
	}

	/**
	 * Seconds until the earliest pending job becomes due — 0 when one is
	 * due now, null when the queue is empty. This is what lets a tick
	 * schedule the next one for a backed-off retry or a send booked for
	 * tomorrow, rather than waiting for someone to open wp-admin.
	 *
	 * @return int|null
	 */
	public static function seconds_until_next() {
		global $wpdb;

		$table = self::table();
		$next  = $wpdb->get_var(
			$wpdb->prepare( "SELECT MIN(available_at) FROM `{$table}` WHERE status = %s", self::STATUS_PENDING )
		);

		if ( ! $next ) {
			return null;
		}

		return \max( 0, (int) \strtotime( $next . ' UTC' ) - \time() );
	}

	/* ------------------------------------------------------------------ */

	private static function token() {
		if ( \function_exists( 'wp_generate_password' ) ) {
			return \substr( \md5( \wp_generate_password( 24, false ) . \microtime( true ) ), 0, 32 );
		}
		return \substr( \md5( \uniqid( 'upnq', true ) ), 0, 32 );
	}

	private static function now() {
		return \function_exists( 'current_time' ) ? \current_time( 'mysql', true ) : \gmdate( 'Y-m-d H:i:s' );
	}

	private static function in_seconds( $seconds ) {
		return \gmdate( 'Y-m-d H:i:s', \time() + (int) $seconds );
	}

	private static function ago( $minutes ) {
		return \gmdate( 'Y-m-d H:i:s', \time() - ( (int) $minutes * 60 ) );
	}

}
