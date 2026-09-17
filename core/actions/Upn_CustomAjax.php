<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Custom ajax call
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;

class Upn_CustomAjax {

	/**
	 * Capability value meaning "no login required".
	 *
	 * @var string
	 */
	const CAP_PUBLIC = 'public';

	function __construct() {
		add_action( 'wp_ajax_upn_ajax', array( $this, 'upn_ajax' ) );
		add_action( 'wp_ajax_nopriv_upn_ajax', array( $this, 'upn_ajax' ) );
	}

	/**
	 * Handlers this endpoint is allowed to dispatch to, and the capability each
	 * one requires.
	 *
	 * This endpoint used to take a "Class@method" string from the request and
	 * invoke it reflectively on any class in the plugin namespace, from an
	 * unauthenticated request. Every reachable handler is now named explicitly
	 * and gated, so an unlisted or unauthorised target is rejected before any
	 * object is constructed.
	 *
	 * @return array<string, string> map of "namespace\Class@method" => required capability
	 */
	private static function allowed_handlers() {
		$handlers = array(
			// A logged-in user registering their own browser for push.
			'admin\options\functions\AppConfig@cs_update_token'         => 'read',
			// Firebase / app configuration — administrators only.
			'admin\options\functions\AppConfig@save'                    => 'manage_options',
			// Web Push key management — administrators only.
			'admin\options\functions\AppConfig@generate_vapid_keys'     => 'manage_options',
			'admin\options\functions\AppConfig@save_vapid_keys'         => 'manage_options',
			// A user saving their own notification preferences.
				// "Send test notification" row action on the device screens.
			'admin\functions\SendNotifications@send_test_notifications' => 'read',
			// Compose screen. The handlers re-check the filterable compose capability.
			'admin\functions\Compose@count'                             => 'read',
			'admin\functions\Compose@send'                              => 'read',
			'admin\functions\Compose@preview'                           => 'read',
			// Web Push: the service-worker client registering / unregistering.
			// "public" means no login required — anonymous visitors subscribe too.
			// The handlers themselves scope what a logged-out caller may touch.
			'admin\functions\Subscriptions@save'                        => self::CAP_PUBLIC,
			'admin\functions\Subscriptions@remove'                      => self::CAP_PUBLIC,
		);

		/**
		 * Register more handlers. A value is a capability, or an array with
		 * 'cap' and 'class' (a fully-qualified class outside this plugin's
		 * namespace, e.g. Pro's). The method is the part after the @.
		 *
		 * @param array<string, string|array{cap:string,class?:string}> $handlers
		 */
		return (array) apply_filters( 'upn_ajax_handlers', $handlers );
	}

	/**
	 * Collapse repeated backslashes so a handler key compares reliably however
	 * the client escaped it.
	 *
	 * @param string $handler
	 * @return string
	 */
	private static function normalize_handler( $handler ) {
		$handler = (string) $handler;
		while ( false !== \strpos( $handler, '\\\\' ) ) {
			$handler = \str_replace( '\\\\', '\\', $handler );
		}
		return \trim( $handler, '\\' );
	}

	/**
	 * Send a JSON error and stop.
	 *
	 * @param string $title
	 * @param string $text
	 * @return void
	 */
	private static function fail( $title, $text ) {
		wp_send_json(
			array(
				'status' => false,
				'title'  => $title,
				'text'   => $text,
			)
		);
	}

	/**
	 * custom ajax call
	 */
	public function upn_ajax() {
		if ( ! isset( $_REQUEST['cs_token'] ) || false === check_ajax_referer( SECURE_AUTH_SALT, 'cs_token', false ) ) {
			self::fail(
				__( 'Invalid token', 'ultimate-push-notifications' ),
				__( 'Sorry! we are unable recognize your auth!', 'ultimate-push-notifications' )
			);
		}

		if ( ! isset( $_REQUEST['data'] ) && isset( $_POST['method'] ) ) {
			$data = Util::check_evil_script( $_POST );
		} elseif ( isset( $_REQUEST['data'] ) ) {
			$data = Util::check_evil_script( $_REQUEST['data'] );
		} else {
			$data = array();
		}

		$handler = isset( $data['method'] ) ? self::normalize_handler( $data['method'] ) : '';

		$allowed = self::allowed_handlers();
		if ( '' === $handler || ! isset( $allowed[ $handler ] ) ) {
			self::fail(
				__( 'Invalid Request', 'ultimate-push-notifications' ),
				__( 'Method parameter missing / invalid!', 'ultimate-push-notifications' )
			);
		}

		$entry    = $allowed[ $handler ];
		$required = \is_array( $entry ) ? ( isset( $entry['cap'] ) ? (string) $entry['cap'] : 'manage_options' ) : (string) $entry;

		if ( self::CAP_PUBLIC !== $required && ( ! is_user_logged_in() || ! current_user_can( $required ) ) ) {
			self::fail(
				__( 'Access Denied', 'ultimate-push-notifications' ),
				__( 'You do not have permission to perform this action.', 'ultimate-push-notifications' )
			);
		}

		list( $class_name, $method_name ) = \explode( '@', $handler, 2 );

		$class_path = \is_array( $entry ) && ! empty( $entry['class'] ) ? '\\' . \ltrim( (string) $entry['class'], '\\' ) : '\\UltimatePushNotifications\\' . $class_name;
		if ( ! class_exists( $class_path ) || ! method_exists( $class_path, $method_name ) ) {
			self::fail(
				__( 'Invalid Library', 'ultimate-push-notifications' ),
				sprintf(
					/* translators: %s: handler name */
					__( 'Handler "%s" is not available.', 'ultimate-push-notifications' ),
					$handler
				)
			);
		}

		echo ( new $class_path() )->{$method_name}( $data );
		exit;
	}

}
