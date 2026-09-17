<?php namespace UltimatePushNotifications\automation;

use UltimatePushNotifications\messaging\Composer;
use UltimatePushNotifications\messaging\MergeTags;

/**
 * Listens for the triggers live rules use, and runs those rules when one
 * fires.
 *
 * Hooks are attached once per trigger, not once per rule, and an occurrence
 * (by Event key) is handled once per request — WooCommerce fires
 * woocommerce_order_status_changed more than once for a single transition
 * on some paths, and two hooks can describe the same checkout.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Engine {

	/** @var array<string,bool> trigger keys already hooked this request */
	private static $bound = array();

	/** @var array<string,bool> event keys already handled this request */
	private static $seen = array();

	/**
	 * Attach after every integration has registered its own hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		\add_action( 'init', array( __CLASS__, 'listen' ), 20 );
	}

	/**
	 * Hook every trigger a live rule uses.
	 *
	 * @return void
	 */
	public static function listen() {
		foreach ( Rules::live() as $rule ) {
			$trigger = TriggerRegistry::get( $rule['trigger'] );
			if ( ! $trigger || isset( self::$bound[ $trigger->key() ] ) ) {
				continue;
			}
			self::$bound[ $trigger->key() ] = true;

			foreach ( $trigger->hooks() as $hook => $argc ) {
				\add_action(
					$hook,
					function () use ( $trigger ) {
						self::fire( $trigger, \func_get_args() );
					},
					20,
					(int) $argc
				);
			}
		}
	}

	/**
	 * A hook fired: build the Event and run every live rule on that trigger.
	 *
	 * @param Trigger $trigger
	 * @param array   $args   Hook arguments.
	 * @return array<int,mixed> rule id => Composer::send() result, for the rules that ran.
	 */
	public static function fire( Trigger $trigger, array $args ) {
		$event = $trigger->build( $args );
		if ( ! $event ) {
			return array();
		}

		if ( isset( self::$seen[ $event->key ] ) ) {
			return array();
		}
		self::$seen[ $event->key ] = true;

		/**
		 * An automation event was built, before any rule runs.
		 *
		 * @param Event $event
		 */
		\do_action( 'upn_automation_event', $event );

		$results = array();
		foreach ( Rules::live() as $id => $rule ) {
			if ( $rule['trigger'] !== $trigger->key() ) {
				continue;
			}
			$result = self::run( $rule, $event );
			if ( null !== $result ) {
				$results[ $id ] = $result;
			}
		}
		return $results;
	}

	/**
	 * Run one rule against one event.
	 *
	 * @param array $rule
	 * @param Event $event
	 * @return array|\WP_Error|null null when the rule did not apply.
	 */
	public static function run( array $rule, Event $event ) {
		if ( ! self::matches( $rule, $event ) ) {
			return null;
		}

		$audience = Audience::resolve( $rule, $event );
		if ( null === $audience ) {
			return null;
		}

		$context = $event->context;
		$context['rule'] = $rule;

		/*
		 * Render before sanitising: a URL of "{order_admin_url}" is not a URL
		 * until the tag is resolved. A URL field that renders to something
		 * that is not a URL (a tag the event did not supply) is dropped on its
		 * own rather than failing the notification. Send with an empty context
		 * so nothing is rendered twice.
		 */
		$urls = array(
			'click_action' => '' !== $rule['url'] ? MergeTags::render( $rule['url'], $context ) : $event->url,
			'icon'         => '' !== $rule['icon'] ? MergeTags::render( $rule['icon'], $context ) : (string) \get_site_icon_url( 192 ),
			'image'        => '' !== $rule['image'] ? MergeTags::render( $rule['image'], $context ) : $event->image,
		);
		foreach ( $urls as $k => $v ) {
			$urls[ $k ] = \preg_match( '#^https?://#i', (string) $v ) ? (string) $v : '';
		}

		$fields = Composer::sanitize(
			$urls + array(
				'title' => MergeTags::render( $rule['title'], $context ),
				'body'  => MergeTags::render( $rule['body'], $context ),
				// Re-notifying the same occurrence under the same rule replaces the notification rather than stacking.
				'tag'   => 'upn-a' . (int) $rule['id'] . '-' . \substr( \md5( $event->key ), 0, 12 ),
			)
		);
		if ( \is_wp_error( $fields ) ) {
			return $fields;
		}

		/**
		 * Filter the notification an automation is about to send; return a WP_Error to abort.
		 *
		 * @param array $fields
		 * @param array $rule
		 * @param Event $event
		 */
		$fields = \apply_filters( 'upn_automation_fields', $fields, $rule, $event );
		if ( \is_wp_error( $fields ) ) {
			return $fields;
		}

		/**
		 * Take over the send: return anything but null and the engine treats it
		 * as the result and does not send. A digest stores the notification here
		 * and sends one summary later.
		 *
		 * @param mixed $handled null to let the engine send.
		 * @param array $fields
		 * @param array $audience
		 * @param array $rule
		 * @param Event $event
		 */
		$result = \apply_filters( 'upn_automation_dispatch', null, $fields, $audience, $rule, $event );
		if ( null === $result ) {
			$result = Composer::send( $fields, $audience, array(), 'automation' );
		}

		/**
		 * An automation ran.
		 *
		 * @param array           $rule
		 * @param Event           $event
		 * @param array|\WP_Error $result
		 */
		\do_action( 'upn_automation_fired', $rule, $event, $result );

		return $result;
	}

	/**
	 * Does the rule apply to this event? A blank param matches anything.
	 *
	 * @param array $rule
	 * @param Event $event
	 * @return bool
	 */
	public static function matches( array $rule, Event $event ) {
		foreach ( (array) $rule['params'] as $key => $wanted ) {
			if ( '' === (string) $wanted ) {
				continue;
			}
			if ( (string) $wanted !== $event->param( $key ) ) {
				return false;
			}
		}

		/**
		 * The seam for conditional rules: return false to skip.
		 *
		 * @param bool  $matches
		 * @param array $rule
		 * @param Event $event
		 */
		return (bool) \apply_filters( 'upn_automation_rule_matches', true, $rule, $event );
	}

	/**
	 * Forget per-request state (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$bound = array();
		self::$seen  = array();
	}

}
