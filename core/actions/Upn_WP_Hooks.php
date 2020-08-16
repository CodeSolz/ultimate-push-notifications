<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Register custom menu
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\notifications\Upn_UserRegistration;

if ( ! \class_exists( 'Upn_WP_Hooks' ) ) {

	class Upn_WP_Hooks {

		function __construct() {
			add_action('user_register', array( $this, 'upn_on_user_registrations') );
			add_action('init', array( $this, 'upn_on_user_registrations') );
		}

		/**
		 * When new user get registered
		 *
		 * @param [type] $user_id
		 * @return void
		 */
		public function upn_on_user_registrations( $user_id ){
			return Upn_UserRegistration::on_user_registration( $user_id );
		}

		
	}

}
