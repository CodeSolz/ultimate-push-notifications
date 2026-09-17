<?php namespace UltimatePushNotifications\queue;

/**
 * WP-Cron driver.
 *
 * The fallback every WordPress site has. Single events only — a recurring
 * schedule would keep firing on a site with an empty queue, and the tick
 * re-schedules itself while work remains.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class CronDriver implements QueueDriverInterface {

	/**
	 * The hook Runner::tick() is attached to. Shared with the Action Scheduler
	 * driver so the runner is driver-agnostic.
	 *
	 * @var string
	 */
	const HOOK = 'upn_queue_tick';

	public function is_available() {
		return \function_exists( 'wp_schedule_single_event' );
	}

	public function schedule( $delay = 0 ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$when = \time() + \max( 0, (int) $delay );

		/*
		 * One tick at a time — but the earliest one asked for. A tick booked
		 * for a send tomorrow must not block a request for one now (a
		 * fan-out that just got queued), so a sooner request moves it.
		 */
		$booked = \wp_next_scheduled( self::HOOK );
		if ( $booked ) {
			if ( (int) $booked <= $when ) {
				return true;
			}
			\wp_clear_scheduled_hook( self::HOOK );
		}

		return (bool) \wp_schedule_single_event( $when, self::HOOK );
	}

	public function is_scheduled() {
		return \function_exists( 'wp_next_scheduled' ) && (bool) \wp_next_scheduled( self::HOOK );
	}

	public function cancel() {
		if ( \function_exists( 'wp_clear_scheduled_hook' ) ) {
			\wp_clear_scheduled_hook( self::HOOK );
		}
	}

	public function name() {
		return 'cron';
	}

}
