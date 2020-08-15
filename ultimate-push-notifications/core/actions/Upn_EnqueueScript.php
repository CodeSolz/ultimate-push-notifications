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

	}
}



