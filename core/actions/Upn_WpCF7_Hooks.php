<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Contact form 7 hooks
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\notifications\Upn_WpCf7;

class Upn_WpCF7_Hooks {

	function __construct() {
		add_action( 'wpcf7_before_send_mail', array( $this, 'upn_wpcf7_before_send_mail' ), 15, 3 );
	}

	/**
	 * When user submit a form
	 *
	 * @param [type] $cf
	 * @return void
	 */
	public function upn_wpcf7_before_send_mail( $contact_form, $abort, $submission ) {
		return Upn_WpCf7::upn_on_form_submission( $contact_form, $abort, $submission );
	}

}

