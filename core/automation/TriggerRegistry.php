<?php namespace UltimatePushNotifications\automation;

use UltimatePushNotifications\automation\triggers\WordPressTriggers;
use UltimatePushNotifications\automation\triggers\WooCommerceTriggers;
use UltimatePushNotifications\automation\triggers\BuddyPressTriggers;
use UltimatePushNotifications\automation\triggers\ContactForm7Triggers;
use UltimatePushNotifications\automation\triggers\MembershipTriggers;

/**
 * Every trigger the site could automate on.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class TriggerRegistry {

	/** @var Trigger[]|null keyed by trigger key */
	private static $triggers = null;

	/**
	 * @return Trigger[]
	 */
	public static function all() {
		if ( null !== self::$triggers ) {
			return self::$triggers;
		}

		$defs = \array_merge(
			WordPressTriggers::definitions(),
			WooCommerceTriggers::definitions(),
			BuddyPressTriggers::definitions(),
			ContactForm7Triggers::definitions(),
			MembershipTriggers::definitions()
		);

		/**
		 * Register or alter triggers.
		 *
		 * @param array $defs Trigger definitions, see Trigger::__construct().
		 */
		$defs = \apply_filters( 'upn_automation_triggers', $defs );

		self::$triggers = array();
		foreach ( (array) $defs as $def ) {
			if ( ! \is_array( $def ) || empty( $def['key'] ) || ! \preg_match( '/^[a-z0-9]+\.[a-z0-9_]+$/', $def['key'] ) ) {
				continue;
			}
			self::$triggers[ $def['key'] ] = new Trigger( $def );
		}

		return self::$triggers;
	}

	/**
	 * @param string $key
	 * @return Trigger|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * @return Trigger[] Only those whose integration is active.
	 */
	public static function available() {
		return \array_filter( self::all(), function ( Trigger $t ) { return $t->available(); } );
	}

	/**
	 * @return array<string,Trigger[]> group label => triggers, every group, available or not —
	 *                                 the editor shows unavailable ones disabled so the
	 *                                 catalogue is visible before the plugin is installed.
	 */
	public static function grouped() {
		$out = array();
		foreach ( self::all() as $t ) {
			$out[ $t->group() ][ $t->key() ] = $t;
		}
		return $out;
	}

	/**
	 * Drop the cache (tests, or after a filter is added late).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$triggers = null;
	}

}
