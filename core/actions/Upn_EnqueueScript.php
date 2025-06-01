<?php namespace UltimatePushNotifications\Actions;

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

		$this->upn_action_enqueue_scripts();

		// pre_print($hook);
		if ( 'upush-notifier_page_cs-upn-set-notifications' == $hook ) {
			wp_enqueue_script( 'admin.tabs', CS_UPN_PLUGIN_ASSET_URI . 'js/upn.tabs.min.js', false );
		}

	}

	/**
	 * Wp enqueue scripts
	 *
	 * @return void
	 */
	public function upn_action_enqueue_scripts() {
		$AppConfig = AppConfig::get_config();

		if ( ! empty( $AppConfig ) ) {

			global $current_user;
			wp_get_current_user();

			wp_enqueue_script( 'firebase-app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-app.js', array(), CS_UPN_VERSION, true );
			wp_enqueue_script( 'firebase-messaging', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-messaging.js', array(), CS_UPN_VERSION, true );
			wp_enqueue_script( 'init_firebase_app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebaseInit.min.js', array(), CS_UPN_VERSION, true );
			wp_enqueue_script( 'init_upn_app', CS_UPN_PLUGIN_ASSET_URI . 'js/app-upn.js', array(), CS_UPN_VERSION, false );

			// localize scripts
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
				) + (array) $AppConfig
			);

		}

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
