<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Register Frontend Scripts
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <tuhin@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\options\functions\AppConfig;
use UltimatePushNotifications\optin\OptIn;
use UltimatePushNotifications\transport\Vapid;

class Upn_EnqueueScript {

	function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'upn_action_admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'upn_action_enqueue_scripts' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'upn_front_enqueue_scripts' ), 20 );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 */
	public function upn_action_admin_enqueue_scripts( $hook ) {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'admin.app.global', CS_UPN_PLUGIN_ASSET_URI . 'js/upn.admin.global.min.js', false );

		// Visiting "Register My Device" is the user's gesture to subscribe.
		$auto_subscribe = ( 'upush-notifier_page_cs-upn-register-my-device' === $hook );

		$this->upn_action_enqueue_scripts( $auto_subscribe );

		if ( 'upush-notifier_page_cs-upn-set-notifications' == $hook ) {
			wp_enqueue_script( 'admin.tabs', CS_UPN_PLUGIN_ASSET_URI . 'js/upn.tabs.min.js', false );
		}

	}

	/**
	 * Wp enqueue scripts
	 *
	 * Web Push is the default whenever the site has a VAPID key pair. The
	 * Firebase client is loaded only for sites that have not set that up yet
	 * and still carry a legacy configuration, so an upgrade never silently
	 * removes the only working path.
	 *
	 * @param bool $auto_subscribe Subscribe on load (Register My Device page only).
	 * @return void
	 */
	public function upn_action_enqueue_scripts( $auto_subscribe = false ) {

		if ( Vapid::has_keys() ) {
			$this->enqueue_webpush_client( $auto_subscribe );
			return;
		}

		$AppConfig = AppConfig::get_config();
		if ( ! empty( $AppConfig ) ) {
			$this->enqueue_firebase_client( $AppConfig );
		}
	}

	/**
	 * The native Web Push client.
	 *
	 * @param bool $auto_subscribe
	 * @return void
	 */
	private function enqueue_webpush_client( $auto_subscribe ) {
		wp_enqueue_script(
			'upn-webpush',
			CS_UPN_PLUGIN_ASSET_URI . 'js/upn-webpush.js',
			array(),
			CS_UPN_VERSION,
			true
		);

		wp_enqueue_style( 'upn-optin', CS_UPN_PLUGIN_ASSET_URI . 'css/upn-optin.css', array(), CS_UPN_VERSION );

		wp_localize_script(
			'upn-webpush',
			'UPN_WebPush',
			array(
				'optin'         => OptIn::client_config(),
				'pwa'           => \UltimatePushNotifications\pwa\Manifest::client_config(),
				'publicKey'     => Vapid::get_public_key(),
				'swUrl'         => Upn_ServiceWorker::url(),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( SECURE_AUTH_SALT ),
				'saveMethod'    => 'admin\functions\Subscriptions@save',
				'removeMethod'  => 'admin\functions\Subscriptions@remove',
				'loggedIn'      => is_user_logged_in(),
				'allowAnonymous' => (bool) apply_filters( 'upn_allow_anonymous_subscriptions', true ),
				'autoSubscribe' => (bool) $auto_subscribe,
				'i18n'          => array(
					'unsupportedTitle' => __( 'Not supported', 'ultimate-push-notifications' ),
					'unsupportedText'  => __( 'This browser does not support push notifications. On iPhone or iPad, add the site to your Home Screen first.', 'ultimate-push-notifications' ),
					'loginTitle'       => __( 'Please log in', 'ultimate-push-notifications' ),
					'loginText'        => __( 'You need to be logged in to register this device.', 'ultimate-push-notifications' ),
					'deniedTitle'      => __( 'Permission denied', 'ultimate-push-notifications' ),
					'deniedText'       => __( 'Notifications were blocked. You can allow them again from your browser\'s site settings.', 'ultimate-push-notifications' ),
					'errorTitle'       => __( 'Could not register', 'ultimate-push-notifications' ),
				),
			)
		);
	}

	/**
	 * The legacy Firebase client.
	 *
	 * @param array $AppConfig
	 * @return void
	 */
	private function enqueue_firebase_client( $AppConfig ) {
		global $current_user;
		wp_get_current_user();

		$firebase_sdk = '11.0.0';
		wp_enqueue_script( 'firebase-app', 'https://www.gstatic.com/firebasejs/' . $firebase_sdk . '/firebase-app-compat.js', array(), null, true );
		wp_enqueue_script( 'firebase-messaging', 'https://www.gstatic.com/firebasejs/' . $firebase_sdk . '/firebase-messaging-compat.js', array( 'firebase-app' ), null, true );
		wp_enqueue_script( 'init_firebase_app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebaseInit.min.js', array( 'firebase-messaging' ), CS_UPN_VERSION, true );
		wp_enqueue_script( 'init_upn_app', CS_UPN_PLUGIN_ASSET_URI . 'js/app-upn.js', array(), CS_UPN_VERSION, false );

		// Only expose safe public-facing config values — never the FCM server key
		$public_config = array(
			'vapidKey' => isset( $AppConfig['vapidKey'] ) ? $AppConfig['vapidKey'] : '',
		);

		wp_localize_script(
			'init_upn_app',
			'UPN_Notifier',
			array(
				'asset_url'    => CS_UPN_PLUGIN_ASSET_URI,
				'ajax_url'     => esc_url( admin_url( 'admin-ajax.php?action=upn_ajax&cs_token=' . wp_create_nonce( SECURE_AUTH_SALT ) ) ),
				'current_user' => array(
					'user_id'   => isset( $current_user->ID ) ? $current_user->ID : '',
					'user_name' => isset( $current_user->user_login ) ? $current_user->user_login : '',
				),
			) + $public_config
		);
	}

	/**
	 * Register script on frontend
	 *
	 * @return void
	 */
	public function upn_front_enqueue_scripts( $hook ) {

		$url_slug = Util::current_url_slugs();
		if ( isset( $url_slug[2] ) && ! empty( $url_slug[2] ) &&
			( isset( $url_slug['3'] ) && $url_slug['3'] == 'notifications' && isset( $url_slug['4'] ) && $url_slug['4'] == 'push-notifications' )
		   ) {
			wp_enqueue_style(
				'upn-bp-style',
				CS_UPN_PLUGIN_ASSET_URI . 'css/upn-bp-style.min.css',
				array(),
				CS_UPN_VERSION
			);
		}

	}



}
