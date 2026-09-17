<?php namespace UltimatePushNotifications\transport;

/**
 * Minimal user-agent classification.
 *
 * Enough to answer "which browser family, which OS, phone or desktop" for a
 * subscriber list and for the health report ("opt-ins dropped after Chrome
 * 140"). Deliberately not a full parser: version strings and rare browsers
 * are not worth a dependency, and a wrong guess here costs a mislabelled row,
 * not a failed send.
 *
 * @package Transport
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class UserAgent {

	/**
	 * Classify a UA string.
	 *
	 * @param string $ua
	 * @return array{browser:string, os:string, device:string}
	 */
	public static function parse( $ua ) {
		$ua = (string) $ua;

		return array(
			'browser' => self::browser( $ua ),
			'os'      => self::os( $ua ),
			'device'  => self::device( $ua ),
		);
	}

	/**
	 * Browser family. Order matters: many UAs claim Chrome and Safari at once.
	 *
	 * @param string $ua
	 * @return string
	 */
	public static function browser( $ua ) {
		if ( '' === $ua ) {
			return 'unknown';
		}

		$checks = array(
			'edge'    => '/\bEdg(?:e|A|iOS)?\/\d/',
			'opera'   => '/\b(?:OPR|Opera)\/\d/',
			'samsung' => '/\bSamsungBrowser\/\d/',
			'brave'   => '/\bBrave\b/',
			'vivaldi' => '/\bVivaldi\/\d/',
			'firefox' => '/\b(?:Firefox|FxiOS)\/\d/',
			'chrome'  => '/\b(?:Chrome|CriOS)\/\d/',
			'safari'  => '/\bSafari\/\d/',
		);

		foreach ( $checks as $name => $pattern ) {
			if ( \preg_match( $pattern, $ua ) ) {
				return $name;
			}
		}

		return 'other';
	}

	/**
	 * Operating system family.
	 *
	 * @param string $ua
	 * @return string
	 */
	public static function os( $ua ) {
		if ( '' === $ua ) {
			return 'unknown';
		}

		// iPadOS 13+ reports as Macintosh; the touch hint gives it away.
		if ( \preg_match( '/\biPad\b|Macintosh.*Mobile/', $ua ) ) {
			return 'ios';
		}

		$checks = array(
			'ios'      => '/\b(?:iPhone|iPod)\b/',
			'android'  => '/\bAndroid\b/',
			'windows'  => '/\bWindows\b/',
			'chromeos' => '/\bCrOS\b/',
			'macos'    => '/\bMac OS X\b|\bMacintosh\b/',
			'linux'    => '/\bLinux\b/',
		);

		foreach ( $checks as $name => $pattern ) {
			if ( \preg_match( $pattern, $ua ) ) {
				return $name;
			}
		}

		return 'other';
	}

	/**
	 * Form factor.
	 *
	 * @param string $ua
	 * @return string desktop|mobile|tablet|unknown
	 */
	public static function device( $ua ) {
		if ( '' === $ua ) {
			return 'unknown';
		}

		if ( \preg_match( '/\biPad\b|\bTablet\b|Macintosh.*Mobile/', $ua ) ) {
			return 'tablet';
		}

		if ( \preg_match( '/\bAndroid\b/', $ua ) && ! \preg_match( '/\bMobile\b/', $ua ) ) {
			return 'tablet';
		}

		if ( \preg_match( '/\bMobile\b|\biPhone\b|\biPod\b/', $ua ) ) {
			return 'mobile';
		}

		return 'desktop';
	}

}
