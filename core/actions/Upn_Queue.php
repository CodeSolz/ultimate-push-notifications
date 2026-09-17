<?php namespace UltimatePushNotifications\actions;

/**
 * Boots the background queue.
 *
 * Lives in core/actions so the plugin's hook loader picks it up with the
 * others. Attaches the runner to its cron/Action Scheduler hook, registers the
 * send job, and keeps housekeeping on a daily schedule.
 *
 * @package Action
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\queue\BroadcastJob;
use UltimatePushNotifications\queue\JobRepository;
use UltimatePushNotifications\queue\Runner;
use UltimatePushNotifications\queue\SendJob;
use UltimatePushNotifications\transport\DeliveryLog;

class Upn_Queue {

	/**
	 * Daily housekeeping hook.
	 *
	 * @var string
	 */
	const HOUSEKEEPING_HOOK = 'upn_daily_housekeeping';

	function __construct() {
		Runner::boot();
		SendJob::boot();
		BroadcastJob::boot();

		add_action( 'init', array( $this, 'schedule_housekeeping' ) );
		add_action( self::HOUSEKEEPING_HOOK, array( $this, 'housekeeping' ) );

		/*
		 * A tick that was scheduled but never ran (cron disabled, site idle)
		 * leaves work waiting. Nudge the runner on admin page loads when jobs
		 * are due — cheap, and it means a site whose cron is broken still
		 * drains its queue whenever someone is logged in.
		 */
		add_action( 'admin_init', array( $this, 'nudge' ) );
	}

	/**
	 * Make sure the daily housekeeping event exists.
	 *
	 * @return void
	 */
	public function schedule_housekeeping() {
		if ( ! wp_next_scheduled( self::HOUSEKEEPING_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOUSEKEEPING_HOOK );
		}
	}

	/**
	 * Prune old log rows and parked jobs.
	 *
	 * @return void
	 */
	public function housekeeping() {
		DeliveryLog::prune();

		/**
		 * Filter whether parked (permanently failed) jobs are purged daily.
		 *
		 * Off by default so a site can inspect why jobs failed. Turn on once
		 * the health report is enough.
		 *
		 * @param bool $purge
		 */
		if ( apply_filters( 'upn_queue_purge_failed_daily', false ) ) {
			JobRepository::purge_failed();
		}

		/**
		 * Daily housekeeping ran. Add-ons prune their own tables here.
		 */
		do_action( 'upn_queue_housekeeping' );
	}

	/**
	 * Schedule a tick if work is waiting and none is pending.
	 *
	 * @return void
	 */
	public function nudge() {
		if ( ! Runner::enabled() ) {
			return;
		}

		if ( JobRepository::pending_count() > 0 && ! Runner::driver()->is_scheduled() ) {
			Runner::schedule( 0 );
		}
	}

}
