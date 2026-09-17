<?php namespace UltimatePushNotifications\pwa;

use UltimatePushNotifications\optin\OptIn;

/**
 * The web app manifest, so the site can be added to a Home Screen.
 *
 * Web push on iPhone and iPad works only for a site installed to the Home
 * Screen, and installing needs a manifest. This serves one at
 * home_url('/?upn_manifest=1') — no file on disk, no rewrite rule, the same
 * way the service worker is served — and adds the link and the Apple meta
 * tags to every page. If another plugin already provides a manifest, this
 * one steps aside.
 *
 * @package UltimatePushNotifications
 * @since 1.6.2
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Manifest {

	const OPTION    = 'upn_pwa';
	const QUERY_VAR = 'upn_manifest';

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'          => false,
			'short_name'       => '',
			'theme_color'      => '#ffffff',
			'background_color' => '#ffffff',
			'display'          => 'standalone',
			'ios_title'        => \__( 'Get notified on your iPhone', 'ultimate-push-notifications' ),
			'ios_text'         => \__( 'Tap Share, then “Add to Home Screen”. Open the app from there and you can turn on notifications.', 'ultimate-push-notifications' ),
			'ios_ok'           => \__( 'Got it', 'ultimate-push-notifications' ),
		);
	}

	/**
	 * @return array
	 */
	public static function settings() {
		$saved = \get_option( self::OPTION );
		return self::sanitize( \is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public static function sanitize( $input ) {
		$d     = self::defaults();
		$input = \is_array( $input ) ? $input : array();
		$text  = function ( $key, $max ) use ( $input, $d ) {
			$v = isset( $input[ $key ] ) ? \wp_strip_all_tags( \trim( (string) $input[ $key ] ) ) : $d[ $key ];
			return \mb_substr( '' === $v ? $d[ $key ] : $v, 0, $max );
		};
		$color = function ( $key ) use ( $input, $d ) {
			return isset( $input[ $key ] ) && \preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $input[ $key ] ) ? \strtolower( (string) $input[ $key ] ) : $d[ $key ];
		};
		return array(
			'enabled'          => ! empty( $input['enabled'] ),
			'short_name'       => \mb_substr( isset( $input['short_name'] ) ? \wp_strip_all_tags( \trim( (string) $input['short_name'] ) ) : '', 0, 12 ),
			'theme_color'      => $color( 'theme_color' ),
			'background_color' => $color( 'background_color' ),
			'display'          => isset( $input['display'] ) && \in_array( $input['display'], array( 'standalone', 'minimal-ui', 'browser' ), true ) ? $input['display'] : $d['display'],
			'ios_title'        => $text( 'ios_title', 80 ),
			'ios_text'         => $text( 'ios_text', 240 ),
			'ios_ok'           => $text( 'ios_ok', 30 ),
		);
	}

	/**
	 * @return string
	 */
	public static function url() {
		return \add_query_arg( self::QUERY_VAR, '1', \home_url( '/' ) );
	}

	/**
	 * Another plugin already provides a manifest?
	 *
	 * @return bool
	 */
	public static function deferred() {
		$other = \defined( 'SUPERPWA_VERSION' ) || \defined( 'PWA_VERSION' ) || \defined( 'PWAFORWP_VERSION' ) || \function_exists( 'pwa_get_manifest' );
		/**
		 * Whether to leave the manifest to another plugin or the theme.
		 *
		 * @param bool $other
		 */
		return (bool) \apply_filters( 'upn_pwa_deferred', $other );
	}

	/**
	 * Is the manifest ours to serve and link?
	 *
	 * @return bool
	 */
	public static function active() {
		return self::settings()['enabled'] && ! self::deferred();
	}

	/**
	 * The site icon at the sizes a manifest wants.
	 *
	 * @return array[] src, sizes, type, purpose
	 */
	public static function icons() {
		if ( ! \function_exists( 'get_site_icon_url' ) || ! \has_site_icon() ) {
			return array();
		}
		$icons = array();
		foreach ( array( 192, 512 ) as $size ) {
			$url = (string) \get_site_icon_url( $size );
			if ( '' === $url ) {
				continue;
			}
			$icons[] = array( 'src' => $url, 'sizes' => $size . 'x' . $size, 'type' => self::mime( $url ), 'purpose' => 'any' );
		}
		return $icons;
	}

	/**
	 * @return string the short name, from the setting or the site's title
	 */
	public static function short_name() {
		$s = self::settings();
		if ( '' !== $s['short_name'] ) {
			return $s['short_name'];
		}
		$name = \trim( (string) \get_bloginfo( 'name' ) );
		return \mb_substr( '' === $name ? 'App' : $name, 0, 12 );
	}

	/**
	 * The manifest as data.
	 *
	 * @return array
	 */
	public static function build() {
		$s    = self::settings();
		$name = \trim( (string) \get_bloginfo( 'name' ) );
		$m    = array(
			'id'               => '/',
			'name'             => '' === $name ? self::short_name() : $name,
			'short_name'       => self::short_name(),
			'description'      => (string) \get_bloginfo( 'description' ),
			'start_url'        => \home_url( '/' ),
			'scope'            => \home_url( '/' ),
			'display'          => $s['display'],
			'background_color' => $s['background_color'],
			'theme_color'      => $s['theme_color'],
			'lang'             => (string) \get_bloginfo( 'language' ),
			'icons'            => self::icons(),
		);
		/**
		 * The manifest before it is served.
		 *
		 * @param array $manifest
		 */
		return (array) \apply_filters( 'upn_pwa_manifest', $m );
	}

	/**
	 * The tags for the head of every page.
	 *
	 * @return string
	 */
	public static function head() {
		if ( ! self::active() ) {
			return '';
		}
		$s   = self::settings();
		$out = '<link rel="manifest" href="' . \esc_url( self::url() ) . '" />' . "\n";
		$out .= '<meta name="theme-color" content="' . \esc_attr( $s['theme_color'] ) . '" />' . "\n";
		$out .= '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
		$out .= '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
		$out .= '<meta name="apple-mobile-web-app-title" content="' . \esc_attr( self::short_name() ) . '" />' . "\n";
		if ( \function_exists( 'has_site_icon' ) && \has_site_icon() ) {
			$out .= '<link rel="apple-touch-icon" href="' . \esc_url( (string) \get_site_icon_url( 180 ) ) . '" />' . "\n";
		}
		return $out;
	}

	/**
	 * What the client needs.
	 *
	 * @return array
	 */
	public static function client_config() {
		$s = self::settings();
		return array(
			'enabled' => self::active(),
			'ios'     => array( 'title' => $s['ios_title'], 'text' => $s['ios_text'], 'ok' => $s['ios_ok'] ),
		);
	}

	/**
	 * @param string $url
	 * @return string
	 */
	private static function mime( $url ) {
		$ext = \strtolower( (string) \pathinfo( (string) \wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		switch ( $ext ) {
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
			case 'gif':
				return 'image/gif';
			case 'webp':
				return 'image/webp';
			case 'svg':
				return 'image/svg+xml';
			case 'ico':
				return 'image/x-icon';
			default:
				return 'image/png';
		}
	}

}
