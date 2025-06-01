<?php namespace UltimatePushNotifications\actions;

/**
 * Class: The Event Calendar Hooks
 *
 * @package Action
 * @since 1.1.3
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\UpnDeviceManager;

class Upn_DaEvCalendar_Hooks {

	function __construct() {
		
		// pre_print(
		// 	UpnDeviceManager::get_registered_users()
		// );

	}

	/**
	 * When user submit a form
	 *
	 * @param [type] $cf
	 * @return void
	 */
	public function upn_wpcf7_before_send_mail( $contact_form, $abort, $submission ) {
		
	}

}

