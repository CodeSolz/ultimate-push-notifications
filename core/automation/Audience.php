<?php namespace UltimatePushNotifications\automation;

use UltimatePushNotifications\transport\Subscription;

/**
 * Turns a rule's audience choice plus an Event into the audience array the
 * subscriber store understands.
 *
 * Two people are always left out unless the rule says otherwise: the actor
 * (nobody needs a push about their own order-status click) and anyone who
 * muted the rule.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Audience {

	/**
	 * @param array $rule
	 * @param Event $event
	 * @return array|null Store audience, or null when nobody could match.
	 */
	public static function resolve( array $rule, Event $event ) {
		$exclude = self::excluded( $rule, $event );
		$base    = array( 'transport' => Subscription::TRANSPORT_WEBPUSH );

		switch ( $rule['audience'] ) {
			case 'everyone':
				$audience = $base + array( 'who' => 'any', 'exclude_user_ids' => $exclude );
				break;

			case 'visitors':
				$audience = $base + array( 'who' => 'anonymous' );
				break;

			case 'members':
				$audience = $base + array( 'who' => 'logged_in', 'exclude_user_ids' => $exclude );
				break;

			case 'roles':
				if ( ! $rule['roles'] ) {
					return null;
				}
				$audience = $base + array( 'who' => 'roles', 'roles' => $rule['roles'], 'exclude_user_ids' => $exclude );
				break;

			case 'users':
				$ids = \array_values( \array_diff( $rule['user_ids'], $exclude ) );
				if ( ! $ids ) {
					return null;
				}
				$audience = $base + array( 'who' => 'logged_in', 'user_ids' => $ids );
				break;

			default:
				// An event-specific audience, e.g. "buyer".
				$ids = \array_values( \array_diff( $event->people( $rule['audience'] ), $exclude ) );
				if ( ! $ids ) {
					return null;
				}
				$audience = $base + array( 'who' => 'logged_in', 'user_ids' => $ids );
		}

		/**
		 * Filter the resolved audience for an automation; return null to send to nobody.
		 *
		 * @param array|null $audience
		 * @param array      $rule
		 * @param Event      $event
		 */
		return \apply_filters( 'upn_automation_audience', $audience, $rule, $event );
	}

	/**
	 * @param array $rule
	 * @param Event $event
	 * @return int[]
	 */
	public static function excluded( array $rule, Event $event ) {
		$ids = array();
		if ( ! $rule['notify_actor'] && $event->actor > 0 ) {
			$ids[] = $event->actor;
		}
		if ( ! empty( $rule['id'] ) ) {
			$ids = \array_merge( $ids, Preferences::muted_users( $rule['id'] ) );
		}
		return \array_values( \array_unique( \array_map( 'intval', $ids ) ) );
	}

}
