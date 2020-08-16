<?php namespace UltimatePushNotifications\admin\functions;

/**
 * Send notifications
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;


class SendNotifications {

    /**
     * Send Test Notifications
     *
     * @return void
     */
    public function send_test_notifications( $user_input ){

        pre_print( $user_input );

    }


}