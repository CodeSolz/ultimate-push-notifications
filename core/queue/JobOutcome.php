<?php namespace UltimatePushNotifications\queue;

/**
 * What a job handler wants the runner to do next.
 *
 * Returning nothing means "done, delete the job". Returning an outcome with
 * retry set means "put it back, and wait at least this long" — the way a push
 * service's Retry-After reaches the queue without being thrown as an error and
 * burning the exception path.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class JobOutcome {

	/** @var bool */
	public $retry = false;

	/** @var bool Put the job back for later without counting an attempt. */
	public $defer = false;

	/** @var int Seconds to wait before the next attempt. */
	public $delay = 0;

	/** @var string */
	public $reason = '';

	/**
	 * Ask for a retry.
	 *
	 * @param string $reason
	 * @param int    $delay
	 * @return self
	 */
	public static function retry( $reason = '', $delay = 0 ) {
		$outcome         = new self();
		$outcome->retry  = true;
		$outcome->delay  = \max( 0, (int) $delay );
		$outcome->reason = (string) $reason;
		return $outcome;
	}

	/**
	 * Ask to wait: the job is fine, the moment is wrong (quiet hours, a
	 * window that has not opened). Unlike retry(), this spends no attempt,
	 * so a delivery held back every night for a week is not parked as failed.
	 *
	 * @param string $reason
	 * @param int    $delay Seconds.
	 * @return self
	 */
	public static function defer( $reason = '', $delay = 0 ) {
		$outcome         = new self();
		$outcome->defer  = true;
		$outcome->delay  = \max( 0, (int) $delay );
		$outcome->reason = (string) $reason;
		return $outcome;
	}

	/**
	 * Done.
	 *
	 * @return self
	 */
	public static function done() {
		return new self();
	}

}
