<?php namespace UltimatePushNotifications\admin\functions\notifications;

/**
 * Notification Builders
 *
 * @package Notifications
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\admin\options\functions\SetNotifications;

class Upn_UserRegistration{

    /**
     * On user Registration
     *
     * @param [type] $user_id
     * @return void
     */
    public function on_user_registration( $user_id ){
        $user_meta = \get_userdata( $user_id );
        
        if( empty( $user_meta ) ) {
            return;
        }

        $find = array(
            '{user_login}', '{user_role}'
        );

        $icon = CS_UPN_PLUGIN_ASSET_URI . 'img/icon-new-user.png';

        if( $user_meta->roles ){
            foreach( $user_meta->roles as $role ){
                $hasUserAsked = SetNotifications::has_user_asked_for_notification( $user_id, $role );
                $hasUserAsked = array_merge( (array) $hasUserAsked, self::new_user_notification_msg() );
                if( $hasUserAsked ){
                    $response[] = SendNotifications::prepare_send_notifications( (array) $hasUserAsked + array(
                        'find' => $find,
                        'replace' => array( $user_meta->data->user_login, $role ),
                        'icon' => $icon,
                        'click_action' => admin_url( 'users.php' )
                    ) );
                }
            }
        }

        return $response;
    }

    /**
     * get new user 
     * notifications msg
     *
     * @return void
     */
    private static function new_user_notification_msg(){
        return array(
            'title' => __( 'New User Registered!', 'ultimate-push-notifications' ),
            'body' => sprintf( __( "'%s' Registered as '%s'.", 'ultimate-push-notifications' ), '{user_login}', '{user_role}' )
        );
    }

}
