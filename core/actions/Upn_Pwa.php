<?php namespace UltimatePushNotifications\actions;

use UltimatePushNotifications\pwa\Manifest;

/**
 * Serve the web app manifest and put its tags in the head.
 *
 * @package UltimatePushNotifications
 * @since 1.6.2
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Upn_Pwa {

	function __construct() {
		add_action( 'init', array( $this, 'maybe_serve' ), 1 );
		add_action( 'wp_head', array( $this, 'head' ), 1 );
	}

	/**
	 * @return void
	 */
	public function maybe_serve() {
		if ( ! isset( $_GET[ Manifest::QUERY_VAR ] ) ) {
			return;
		}
		if ( ! Manifest::active() ) {
			\status_header( 404 );
			exit;
		}
		\nocache_headers();
		\header( 'Content-Type: application/manifest+json; charset=utf-8' );
		\header( 'X-Content-Type-Options: nosniff' );
		echo \wp_json_encode( Manifest::build(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
		exit;
	}

	/**
	 * @return void
	 */
	public function head() {
		echo Manifest::head(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}

}
