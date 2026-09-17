<?php namespace UltimatePushNotifications\automation;

/**
 * A member's say over which automations reach them.
 *
 * Copy lives on the rule; the member only decides receive-or-not. One meta
 * key per muted rule keeps the "who muted rule 12" question a plain meta
 * query the audience resolver can run in one call.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Preferences {

	const META_PREFIX = 'upn_mute_rule_';

	/**
	 * @param int $rule_id
	 * @return string
	 */
	public static function meta_key( $rule_id ) {
		return self::META_PREFIX . (int) $rule_id;
	}

	/**
	 * @param int $user_id
	 * @param int $rule_id
	 * @return bool
	 */
	public static function is_muted( $user_id, $rule_id ) {
		return '1' === (string) \get_user_meta( (int) $user_id, self::meta_key( $rule_id ), true );
	}

	/**
	 * @param int  $user_id
	 * @param int  $rule_id
	 * @param bool $muted
	 * @return void
	 */
	public static function set_muted( $user_id, $rule_id, $muted ) {
		if ( $muted ) {
			\update_user_meta( (int) $user_id, self::meta_key( $rule_id ), '1' );
		} else {
			\delete_user_meta( (int) $user_id, self::meta_key( $rule_id ) );
		}
	}

	/**
	 * Everyone who muted a rule.
	 *
	 * @param int $rule_id
	 * @return int[]
	 */
	public static function muted_users( $rule_id ) {
		$ids = \get_users(
			array(
				'meta_key'   => self::meta_key( $rule_id ),
				'meta_value' => '1',
				'fields'     => 'ID',
				'number'     => -1,
			)
		);
		return \array_map( 'intval', (array) $ids );
	}

	/**
	 * Rules a member could be reached by, so the settings screen only lists
	 * what applies to them. Visitor-only rules never do.
	 *
	 * @param int $user_id
	 * @return array<int,array>
	 */
	public static function rules_for_user( $user_id ) {
		$user = \get_userdata( (int) $user_id );
		if ( ! $user ) {
			return array();
		}
		$roles = (array) $user->roles;
		$out   = array();

		foreach ( Rules::all() as $id => $rule ) {
			if ( ! $rule['enabled'] ) {
				continue;
			}
			$trigger = TriggerRegistry::get( $rule['trigger'] );
			if ( ! $trigger || ! $trigger->available() ) {
				continue;
			}
			switch ( $rule['audience'] ) {
				case 'visitors':
					continue 2;
				case 'roles':
					if ( ! \array_intersect( $roles, $rule['roles'] ) ) {
						continue 2;
					}
					break;
				case 'users':
					if ( ! \in_array( (int) $user_id, $rule['user_ids'], true ) ) {
						continue 2;
					}
					break;
			}
			$out[ $id ] = $rule;
		}
		return $out;
	}

	/**
	 * Drop every mute a member set (account deletion, GDPR erasure).
	 *
	 * @param int $user_id
	 * @return void
	 */
	public static function forget( $user_id ) {
		foreach ( \array_keys( Rules::all() ) as $rule_id ) {
			\delete_user_meta( (int) $user_id, self::meta_key( $rule_id ) );
		}
	}

}
