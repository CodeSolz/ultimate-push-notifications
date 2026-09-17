<?php namespace UltimatePushNotifications\pro;

/**
 * The single place that answers "is this capability available on this site?".
 *
 * Every module asks the same way:
 *
 *     if ( Entitlements::can( 'automations.unlimited' ) ) { … }
 *
 * The free plugin describes capabilities it does not implement; the Pro
 * plugin answers for them on the `upn_can` filter. Neither side imports the
 * other, and this class never asks whether a plugin is active: a pro key is
 * false until something answers true for it. That keeps Pro's list of what
 * it implements honest — an installed Pro that has not shipped segments yet
 * must not light up the segments UI.
 *
 * An unknown key is false — never true: a typo must not silently unlock a
 * paid feature, and it complains under WP_DEBUG.
 *
 * Ported from the sibling product's Maintenance\Support\Entitlements, which
 * replaced its "does the plan code contain a 3" string hack, with one
 * change: the sibling defaulted every pro key to true whenever the Pro
 * plugin was active, which made its implemented list decorative.
 *
 * @package Pro
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Entitlements {

	/**
	 * Capabilities the free plugin implements itself.
	 *
	 * Listed rather than assumed, so a screen can ask about a free capability
	 * with the same call it uses for a paid one.
	 *
	 * @var string[]
	 */
	private static $free = array(
		'compose.broadcast',
		'autopush.basic',
		'automations.basic',
		'optin.basic',
		'subscribers.list',
		'analytics.recent',
		'health.check',
	);

	/**
	 * Capabilities the Pro plugin may implement.
	 *
	 * A key appears here only when something in Free reads it — a locked
	 * panel, a limit, a gate. Speculative keys make this a wish list rather
	 * than a contract.
	 *
	 * @var string[]
	 */
	private static $pro = array(
		'automations.unlimited',
		'automations.conditions',
		'segments',
		'schedule',
		'analytics.history',
		'analytics.attribution',
		'analytics.goals',
		'health.monitor',
		'compose.actions',
		'templates',
		'ai.copy',
		'ai.explain',
		'ai.segments',
		'cadence',
		'automations.digest',
		'drip',
		'ab',
		'sto',
		'preferences',
		'carriers',
		'api',
		'roles',
		'reports',
		'whitelabel',
		'network',
		'commerce',
		'pwa.install',
		'risk',
	);

	/**
	 * Is this capability available on this site?
	 *
	 * @param string $key e.g. 'segments'.
	 * @return bool
	 */
	public static function can( $key ) {
		$key   = \strtolower( \trim( (string) $key ) );
		$known = self::is_free( $key ) || self::is_pro( $key );

		if ( ! $known && \function_exists( '_doing_it_wrong' ) && \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\_doing_it_wrong( __METHOD__, 'Unknown entitlement key: ' . \esc_html( $key ), '1.6.0' );
		}

		// Free keys are true; pro and unknown keys are false until something answers for them.
		$default = self::is_free( $key );

		/**
		 * Filter one entitlement answer.
		 *
		 * Pro attaches here and returns true for the keys it implements. A
		 * third party can unlock a capability it has implemented itself the
		 * same way. Answering true for an unknown key is ignored by nobody —
		 * so don't.
		 *
		 * @param bool   $default What Free decided on its own.
		 * @param string $key     Entitlement key.
		 * @param bool   $known   Whether the key is registered at all.
		 */
		return (bool) \apply_filters( 'upn_can', $default, $key, $known );
	}

	/**
	 * @return string[]
	 */
	public static function keys() {
		return \array_merge( self::$free, self::$pro );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function is_free( $key ) {
		return \in_array( \strtolower( \trim( (string) $key ) ), self::$free, true );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function is_pro( $key ) {
		return \in_array( \strtolower( \trim( (string) $key ) ), self::$pro, true );
	}

}
