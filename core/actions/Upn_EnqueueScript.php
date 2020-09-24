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

use UltimatePushNotifications\admin\options\functions\AppConfig;

class Upn_EnqueueScript {

	function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'upn_action_admin_enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'upn_action_enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 */
	public function upn_action_admin_enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'admin.app.global', CS_UPN_PLUGIN_ASSET_URI . 'js/upn.admin.global.min.js', false );

		$this->upn_action_enqueue_scripts();

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

			wp_enqueue_script( 'firebase-app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-app.js', array(), '1.0', true );
			wp_enqueue_script( 'firebase-messaging', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-messaging.js', array(), '1.0', true );
			wp_enqueue_script( 'init_firebase_app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebaseInit.min.js', array(), '1.0', true );
			wp_enqueue_script( 'init_upn_app', CS_UPN_PLUGIN_ASSET_URI . 'js/app-upn.js', array(), '1.0', false );

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

}
