<?php namespace UltimatePushNotifications\pro;

/**
 * A feature that is visible in Free and does its work in Pro.
 *
 * Every Pro feature is shown where it would live, not hidden: somebody who
 * cannot see that segments exist cannot decide they want them. What is
 * shown is what the feature does, **the real number from this site** where
 * Free already knows one, and a link. "1,240 subscribers you can only reach
 * all at once" is an argument; "Upgrade for segments" is an advertisement.
 *
 * Nothing here is a dark pattern: the panel says plainly that the feature is
 * part of the paid version, and no control on it pretends to work.
 *
 * @package Pro
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Locked {

	const UPGRADE_URL = 'https://codesolz.net/our-products/wordpress-plugin/ultimate-push-notifications/?utm_source=plugin&utm_medium=wp-admin&utm_campaign=locked-panel';

	/** @var bool styles printed once per request */
	private static $styled = false;

	/**
	 * One question asked one way, so a screen never decides for itself what
	 * "locked" means.
	 *
	 * @param string $entitlement
	 * @return bool
	 */
	public static function unlocked( $entitlement ) {
		return Entitlements::can( (string) $entitlement );
	}

	/**
	 * The marker after a locked feature's label.
	 *
	 * @return string
	 */
	public static function badge() {
		return ' <span class="upn-pro-badge">' . \esc_html__( 'Pro', 'ultimate-push-notifications' ) . '</span>';
	}

	/**
	 * @param string $label
	 * @param string $entitlement Empty for a free feature.
	 * @return string Escaped label, badge appended when locked.
	 */
	public static function label( $label, $entitlement = '' ) {
		$out = \esc_html( (string) $label );
		if ( '' !== $entitlement && ! self::unlocked( $entitlement ) ) {
			$out .= self::badge();
		}
		return $out;
	}

	/**
	 * The panel shown in place of a locked feature.
	 *
	 * @param array $args {
	 *     @type string   $title   What the feature is called.
	 *     @type string   $body    What it does, in a sentence or two.
	 *     @type string[] $points  Bullet points, optional.
	 *     @type string   $measure A real figure from this site, optional.
	 * }
	 * @return string
	 */
	public static function panel( array $args ) {
		$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
		$body    = isset( $args['body'] ) ? (string) $args['body'] : '';
		$points  = isset( $args['points'] ) && \is_array( $args['points'] ) ? $args['points'] : array();
		$measure = isset( $args['measure'] ) ? (string) $args['measure'] : '';

		$out  = self::styles();
		$out .= '<div class="upn-locked">';
		$out .= '<p class="upn-locked__badge">' . \esc_html__( 'Part of Ultimate Push Notifications Pro', 'ultimate-push-notifications' ) . '</p>';

		if ( '' !== $title ) {
			$out .= '<h3>' . \esc_html( $title ) . '</h3>';
		}
		// The site's own number, when Free already knows it — the whole difference between an argument and an advertisement.
		if ( '' !== $measure ) {
			$out .= '<p class="upn-locked__measure"><strong>' . \esc_html( $measure ) . '</strong></p>';
		}
		if ( '' !== $body ) {
			$out .= '<p>' . \esc_html( $body ) . '</p>';
		}
		if ( $points ) {
			$out .= '<ul class="upn-locked__points">';
			foreach ( $points as $point ) {
				$out .= '<li>' . \esc_html( $point ) . '</li>';
			}
			$out .= '</ul>';
		}

		$out .= \sprintf(
			'<p><a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			\esc_url( self::url() ),
			\esc_html__( 'See what Pro adds', 'ultimate-push-notifications' )
		);

		return $out . '</div>';
	}

	/**
	 * Filterable so a bundle or reseller can point it elsewhere.
	 *
	 * @return string
	 */
	public static function url() {
		return (string) \apply_filters( 'upn_upgrade_url', self::UPGRADE_URL );
	}

	/**
	 * @return string
	 */
	private static function styles() {
		if ( self::$styled ) {
			return '';
		}
		self::$styled = true;
		return '<style>'
			. '.upn-locked{max-width:720px;margin:1em 0;padding:1em 1.25em;border:1px dashed #c3c4c7;border-radius:4px;background:#fbfbfc}'
			. '.upn-locked h3{margin:.2em 0 .4em}'
			. '.upn-locked__badge{margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#646970}'
			. '.upn-locked__measure{font-size:15px;margin:.2em 0 .6em}'
			. '.upn-locked__points{margin:.4em 0 .8em 1.2em;list-style:disc}'
			. '.upn-pro-badge{display:inline-block;padding:0 6px;border-radius:3px;background:#fcf0e4;color:#8a4b00;font-size:11px;line-height:18px;vertical-align:middle}'
			. '</style>';
	}

}
