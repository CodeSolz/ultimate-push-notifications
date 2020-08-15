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

if ( ! \class_exists( 'Upn_WP_Hooks' ) ) {

	class Upn_WP_Hooks {

		function __construct() {
			
		}

		
	}

}
