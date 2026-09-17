<?php namespace UltimatePushNotifications\queue;

/**
 * How a queue tick gets scheduled.
 *
 * The queue itself is a table; a driver is only the thing that arranges for
 * Runner::tick() to be called later. Two ship: Action Scheduler when the site
 * already has it (WooCommerce installs it), WP-Cron otherwise. Neither is
 * bundled — a plugin that ships its own copy of a library other plugins also
 * ship is how version conflicts start.
 *
 * @package Queue
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

interface QueueDriverInterface {

	/**
	 * Can this driver be used on this site?
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Arrange for a tick after $delay seconds. Must be idempotent: calling it
	 * while a tick is already pending is a no-op that returns true.
	 *
	 * @param int $delay
	 * @return bool
	 */
	public function schedule( $delay = 0 );

	/**
	 * Is a tick already pending?
	 *
	 * @return bool
	 */
	public function is_scheduled();

	/**
	 * Drop any pending tick.
	 *
	 * @return void
	 */
	public function cancel();

	/**
	 * Short name for diagnostics.
	 *
	 * @return string
	 */
	public function name();

}
