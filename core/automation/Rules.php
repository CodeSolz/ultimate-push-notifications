<?php namespace UltimatePushNotifications\automation;

use UltimatePushNotifications\messaging\Composer;
use UltimatePushNotifications\pro\Entitlements;

/**
 * The automation rules: trigger → audience → message.
 *
 * Stored as one option. A site has a handful of these, they are read on
 * every request the engine listens on, and they change rarely — a table
 * would be more machinery than the data warrants.
 *
 * Free runs a few at a time; the limit is a filter so the Pro seam can lift
 * it without this class knowing about licences.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Rules {

	const OPTION = 'upn_automation_rules';

	/** How many enabled rules run at once without Pro. */
	const FREE_ACTIVE_LIMIT = 3;

	/** Generic audiences every trigger supports; triggers add their own "people". */
	const GENERIC_AUDIENCES = array( 'roles', 'users', 'members', 'visitors', 'everyone' );

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'           => 0,
			'name'         => '',
			'trigger'      => '',
			'enabled'      => true,
			'params'       => array(),
			'audience'     => 'roles',
			'roles'        => array( 'administrator' ),
			'user_ids'     => array(),
			'notify_actor' => false,
			'title'        => '',
			'body'         => '',
			'url'          => '',
			'icon'         => '',
			'image'        => '',
			'conditions'   => array(),
			'extra'        => array(),
			'order'        => 0,
			'created'      => 0,
		);
	}

	/**
	 * @return array<int,array> id => rule, in display order.
	 */
	public static function all() {
		$saved = \get_option( self::OPTION );
		$rules = array();
		foreach ( \is_array( $saved ) ? $saved : array() as $rule ) {
			if ( \is_array( $rule ) && ! empty( $rule['id'] ) ) {
				$rules[ (int) $rule['id'] ] = self::sanitize( $rule );
			}
		}
		\uasort( $rules, function ( $a, $b ) {
			return $a['order'] === $b['order'] ? $a['id'] <=> $b['id'] : $a['order'] <=> $b['order'];
		} );
		return $rules;
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : null;
	}

	/**
	 * Validate and normalise a rule from any source.
	 *
	 * The trigger need not be available (its plugin may be temporarily off);
	 * it must exist in the registry, or the rule cannot mean anything.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize( $input ) {
		$d     = self::defaults();
		$input = \is_array( $input ) ? $input : array();

		$trigger_key = isset( $input['trigger'] ) ? \sanitize_text_field( $input['trigger'] ) : '';
		$trigger     = TriggerRegistry::get( $trigger_key );

		$audience = isset( $input['audience'] ) ? \sanitize_key( $input['audience'] ) : $d['audience'];
		$allowed  = self::GENERIC_AUDIENCES;
		if ( $trigger ) {
			$allowed = \array_merge( $allowed, \array_keys( $trigger->people() ) );
		}
		if ( ! \in_array( $audience, $allowed, true ) ) {
			$audience = $d['audience'];
		}

		$valid_roles = \array_keys( \wp_roles()->roles );
		$roles       = isset( $input['roles'] ) ? \array_map( 'sanitize_key', (array) $input['roles'] ) : $d['roles'];
		$roles       = \array_values( \array_intersect( $roles, $valid_roles ) );

		$user_ids = array();
		if ( isset( $input['user_ids'] ) ) {
			$raw = \is_array( $input['user_ids'] ) ? $input['user_ids'] : \preg_split( '/[\s,]+/', (string) $input['user_ids'] );
			foreach ( (array) $raw as $id ) {
				if ( (int) $id > 0 ) {
					$user_ids[] = (int) $id;
				}
			}
			$user_ids = \array_values( \array_unique( $user_ids ) );
		}

		$params = array();
		if ( $trigger && isset( $input['params'] ) && \is_array( $input['params'] ) ) {
			foreach ( $trigger->param_keys() as $key ) {
				$params[ $key ] = isset( $input['params'][ $key ] ) ? \sanitize_text_field( (string) $input['params'][ $key ] ) : '';
			}
		}

		$text = function ( $key, $max ) use ( $input, $d ) {
			$v = isset( $input[ $key ] ) ? \wp_strip_all_tags( \trim( (string) $input[ $key ] ) ) : $d[ $key ];
			return \mb_substr( $v, 0, $max );
		};
		$url = function ( $key ) use ( $input ) {
			// Merge tags are allowed here, so this is not esc_url_raw: the rendered value is validated at send time.
			$v = isset( $input[ $key ] ) ? \trim( (string) $input[ $key ] ) : '';
			return \mb_substr( \preg_replace( '/[\s"\'<>]/', '', $v ), 0, 500 );
		};

		/**
		 * Conditions are reserved for the Pro conditional engine; Free stores
		 * whatever a sanitiser on this filter returns, and nothing by default.
		 *
		 * @param array $conditions
		 * @param array $input
		 */
		$conditions = \apply_filters( 'upn_automation_sanitize_conditions', array(), isset( $input['conditions'] ) ? $input['conditions'] : array() );

		/**
		 * Anything else a rule carries for an extension (a digest window, a
		 * cap). Sanitisers on this filter own their own keys; Free stores the
		 * array and never reads it.
		 *
		 * @param array $extra
		 * @param array $input The raw rule input ($input['extra'] is the usual source).
		 */
		$extra = \apply_filters( 'upn_automation_sanitize_extra', array(), $input );

		return array(
			'id'           => isset( $input['id'] ) ? (int) $input['id'] : 0,
			'name'         => $text( 'name', 80 ),
			'trigger'      => $trigger ? $trigger->key() : '',
			'enabled'      => ! isset( $input['enabled'] ) ? $d['enabled'] : ! empty( $input['enabled'] ),
			'params'       => $params,
			'audience'     => $audience,
			'roles'        => $roles,
			'user_ids'     => $user_ids,
			'notify_actor' => ! empty( $input['notify_actor'] ),
			'title'        => $text( 'title', Composer::MAX_TITLE ),
			'body'         => $text( 'body', Composer::MAX_BODY ),
			'url'          => $url( 'url' ),
			'icon'         => $url( 'icon' ),
			'image'        => $url( 'image' ),
			'conditions'   => \is_array( $conditions ) ? $conditions : array(),
			'extra'        => \is_array( $extra ) ? $extra : array(),
			'order'        => isset( $input['order'] ) ? (int) $input['order'] : 0,
			'created'      => isset( $input['created'] ) ? (int) $input['created'] : 0,
		);
	}

	/**
	 * What is wrong with a rule, if anything.
	 *
	 * @param array $rule Sanitised.
	 * @return \WP_Error|true
	 */
	public static function validate( array $rule ) {
		if ( '' === $rule['trigger'] ) {
			return new \WP_Error( 'upn_rule_no_trigger', \__( 'Choose what should trigger this automation.', 'ultimate-push-notifications' ) );
		}
		if ( '' === $rule['title'] ) {
			return new \WP_Error( 'upn_rule_no_title', \__( 'A title is required.', 'ultimate-push-notifications' ) );
		}
		if ( 'roles' === $rule['audience'] && ! $rule['roles'] ) {
			return new \WP_Error( 'upn_rule_no_roles', \__( 'Choose at least one role.', 'ultimate-push-notifications' ) );
		}
		if ( 'users' === $rule['audience'] && ! $rule['user_ids'] ) {
			return new \WP_Error( 'upn_rule_no_users', \__( 'Enter at least one user ID.', 'ultimate-push-notifications' ) );
		}
		return true;
	}

	/**
	 * Insert or update.
	 *
	 * @param array $input
	 * @return array|\WP_Error The stored rule.
	 */
	public static function save( array $input ) {
		$rule  = self::sanitize( $input );
		$valid = self::validate( $rule );
		if ( \is_wp_error( $valid ) ) {
			return $valid;
		}

		$all = self::all();

		if ( $rule['id'] <= 0 || ! isset( $all[ $rule['id'] ] ) ) {
			$rule['id']      = $all ? \max( \array_keys( $all ) ) + 1 : 1;
			$rule['created'] = \time();
			$rule['order']   = $all ? \max( \array_column( $all, 'order' ) ) + 1 : 1;
		} else {
			$existing        = $all[ $rule['id'] ];
			$rule['created'] = $existing['created'];
			$rule['order']   = isset( $input['order'] ) ? (int) $input['order'] : $existing['order'];
		}
		if ( '' === $rule['name'] ) {
			$trigger      = TriggerRegistry::get( $rule['trigger'] );
			$rule['name'] = $trigger ? $trigger->label() : $rule['trigger'];
		}

		$all[ $rule['id'] ] = $rule;
		self::store( $all );

		/**
		 * @param array $rule
		 */
		\do_action( 'upn_automation_rule_saved', $rule );

		return $rule;
	}

	/**
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		$all = self::all();
		if ( ! isset( $all[ (int) $id ] ) ) {
			return false;
		}
		unset( $all[ (int) $id ] );
		self::store( $all );
		\do_action( 'upn_automation_rule_deleted', (int) $id );
		return true;
	}

	/**
	 * @param int  $id
	 * @param bool $enabled
	 * @return bool
	 */
	public static function set_enabled( $id, $enabled ) {
		$all = self::all();
		if ( ! isset( $all[ (int) $id ] ) ) {
			return false;
		}
		$all[ (int) $id ]['enabled'] = (bool) $enabled;
		self::store( $all );
		return true;
	}

	/**
	 * How many enabled rules may run at once.
	 *
	 * @return int 0 = unlimited.
	 */
	public static function active_limit() {
		$limit = Entitlements::can( 'automations.unlimited' ) ? 0 : self::FREE_ACTIVE_LIMIT;

		/**
		 * @param int $limit 0 for no limit.
		 */
		return \max( 0, (int) \apply_filters( 'upn_automation_active_limit', $limit ) );
	}

	/**
	 * The rules that will actually fire: enabled, trigger available, within
	 * the active limit in display order.
	 *
	 * @return array<int,array>
	 */
	public static function live() {
		$limit = self::active_limit();
		$live  = array();
		foreach ( self::all() as $id => $rule ) {
			if ( ! $rule['enabled'] ) {
				continue;
			}
			$trigger = TriggerRegistry::get( $rule['trigger'] );
			if ( ! $trigger || ! $trigger->available() ) {
				continue;
			}
			if ( $limit > 0 && \count( $live ) >= $limit ) {
				break;
			}
			$live[ $id ] = $rule;
		}
		return $live;
	}

	/**
	 * Enabled rules held back by the active limit.
	 *
	 * @return int[] rule ids
	 */
	public static function over_limit() {
		$live = self::live();
		$out  = array();
		foreach ( self::all() as $id => $rule ) {
			$trigger = TriggerRegistry::get( $rule['trigger'] );
			if ( $rule['enabled'] && $trigger && $trigger->available() && ! isset( $live[ $id ] ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * @param array $rule
	 * @return string One line describing who gets it.
	 */
	public static function describe_audience( array $rule ) {
		switch ( $rule['audience'] ) {
			case 'roles':
				$names = \wp_roles()->get_names();
				$out   = array();
				foreach ( $rule['roles'] as $r ) {
					$out[] = isset( $names[ $r ] ) ? \translate_user_role( $names[ $r ] ) : $r;
				}
				return \implode( ', ', $out );
			case 'users':
				return \sprintf( \_n( '%d specific user', '%d specific users', \count( $rule['user_ids'] ), 'ultimate-push-notifications' ), \count( $rule['user_ids'] ) );
			case 'members':
				return \__( 'All logged-in subscribers', 'ultimate-push-notifications' );
			case 'visitors':
				return \__( 'All visitor subscribers', 'ultimate-push-notifications' );
			case 'everyone':
				return \__( 'Every subscriber', 'ultimate-push-notifications' );
		}
		$trigger = TriggerRegistry::get( $rule['trigger'] );
		$people  = $trigger ? $trigger->people() : array();
		return isset( $people[ $rule['audience'] ] ) ? $people[ $rule['audience'] ] : $rule['audience'];
	}

	/**
	 * @param array $all
	 * @return void
	 */
	private static function store( array $all ) {
		\update_option( self::OPTION, \array_values( $all ), false );
	}

}
