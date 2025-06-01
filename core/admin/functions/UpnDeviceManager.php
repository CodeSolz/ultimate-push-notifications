<?php namespace UltimatePushNotifications\admin\functions;

/**
 * Device Manager
 *
 * @package Functions
 * @since 1.1.2
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class UpnDeviceManager{

    /**
     * Get Registered users
     *
     * @return void
     */
    public static function get_registered_users(){
        global $wpdb;
        $devices = $wpdb->get_results(
            "SELECT * from {$wpdb->prefix}upn_user_devices order by id DESC"
        );

       pre_print( $devices );
    }

}