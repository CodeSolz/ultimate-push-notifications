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

if ( ! \class_exists( 'Upn_EnqueueScript' ) ) {

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

		}

		/**
		 * Wp enqueue scripts
		 *
		 * @return void
		 */
		public function upn_action_enqueue_scripts() {
			if ( file_exists( CS_UPN_BASE_DIR_PATH . 'assets/plugins/firebase/js/firebaseInit.min.js' ) ) {
				wp_enqueue_script( 'firebase-app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-app.js', array(), '1.0', true );
				wp_enqueue_script( 'firebase-messaging', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-messaging.js', array(), '1.0', true );
				wp_enqueue_script( 'init_firebase_app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebaseInit.min.js', array(), '1.0', true );
			}

		}

	}
}



