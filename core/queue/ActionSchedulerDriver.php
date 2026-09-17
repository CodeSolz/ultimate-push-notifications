<?php namespace UltimatePushNotifications\queue;

/**
 * Action Scheduler driver.
 *
 * Used only when the site already loads Action Scheduler (WooCommerce and many
 * others ship it). It runs from a real cron or a background loop rather than
 * on page views, so a quiet site still drains its queue.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class ActionSchedulerDriver implements QueueDriverInterface {

	const HOOK  = CronDriver::HOOK;
	const GROUP = 'upn-queue';

	public function is_available() {
		return \function_exists( 'as_schedule_single_action' )
			&& \function_exists( 'as_next_scheduled_action' )
			&& \function_exists( 'as_unschedule_all_actions' );
	}

	public function schedule( $delay = 0 ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$delay = \max( 0, (int) $delay );

		// One tick at a time, but the earliest asked for: a sooner request moves a later booking.
		$booked = \as_next_scheduled_action( self::HOOK, null, self::GROUP );
		if ( false !== $booked ) {
			if ( true === $booked || (int) $booked <= \time() + $delay ) {
				return true; // true = already running / async-pending
			}
			\as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}

		if ( 0 === $delay && \function_exists( 'as_enqueue_async_action' ) ) {
			\as_enqueue_async_action( self::HOOK, array(), self::GROUP );
			return true;
		}

		\as_schedule_single_action( \time() + $delay, self::HOOK, array(), self::GROUP );

		return true;
	}

	public function is_scheduled() {
		return $this->is_available() && false !== \as_next_scheduled_action( self::HOOK, null, self::GROUP );
	}

	public function cancel() {
		if ( $this->is_available() ) {
			\as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	public function name() {
		return 'action-scheduler';
	}

}
