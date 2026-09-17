<?php namespace UltimatePushNotifications\actions;

/**
 * Boots the opt-in surfaces.
 *
 * @package Action
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\admin\options\pages\OptInPage;
use UltimatePushNotifications\optin\OptIn;

class Upn_OptIn {

	function __construct() {
		OptIn::boot();
		add_action( 'admin_init', array( OptInPage::class, 'register' ) );
	}

}
