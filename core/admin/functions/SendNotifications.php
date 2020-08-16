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
use UltimatePushNotifications\admin\options\functions\AppConfig;

if( ! \class_exists( 'SendNotifications' ) ) { 

class SendNotifications {

    private $fcm_url = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Send Test Notifications
     *
     * @return void
     */
    public function send_test_notifications( $user_input ){

        global $current_user;
        wp_get_current_user();

        $args = (object) array(
            'to' => Util::check_evil_script($user_input['device_token']),
            'data' => array(
                'title' => __( 'Test Notification', 'ultimate-push-notifications' ),
				'body' => sprintf( __( "Hi %s, I'm test notifications. Hope you will enjoy it!", 'ultimate-push-notifications' ), $current_user->user_login ),
				'icon' => CS_UPN_PLUGIN_ASSET_URI .'/img/icon-push.png',
				'click_action' => site_url(),
            )
        );

        $response = $this->send_notification( $args );

        if( isset( $response['success'] ) && $response['success'] > 0 ){
            return wp_send_json(
				array(
					'status'       => true,
					'title'        => __( 'Success!', 'ultimate-push-notifications' ),
					'text'         => __( "Notification sent successfully.", 'ultimate-push-notifications' )
				)
			);
        }
        else if( isset( $response['failure'] ) && $response['failure'] > 0 ){
            return wp_send_json(
				array(
					'status'       => false,
					'title'        => __( 'Failure!', 'ultimate-push-notifications' ),
					'text'         => __( "Something went wrong. Please check your application configuration properly.", 'ultimate-push-notifications' )
				)
			);
        }else{
            return wp_send_json(
				array(
					'status'       => false,
					'title'        => __( 'Error!', 'ultimate-push-notifications' ),
					'text'         => $response
				)
			);
        }

    }

    /**
     * Prepare Send Notificaitons
     *
     * @param [type] $dataObj
     * @return void
     */
    public static function prepare_send_notifications( $dataObj ){
        $dataObj = \is_object( $dataObj ) ? $dataObj : (object) $dataObj;

        $title = str_replace( $dataObj->find, $dataObj->replace, $dataObj->title );
        $description = str_replace( $dataObj->find, $dataObj->replace, $dataObj->body );
        $response = [];
        if( !empty( $dataObj->tokens ) ){
            foreach( $dataObj->tokens as $item ){
                //send notifications
                $payload = array( 
                            'to' => $item->token,
                            'data' => array(
                                'title' => $title,
                                'body' => $description,
                                'icon' => $dataObj->icon,
                                'click_action' => $dataObj->click_action
                            )
                        );

                $response[] = self::send_notification( $payload );
            }
        }

        return $response;
    }

    /**
     * Send notfication
     *
     * @return void
     */
    private function send_notification( $payload ){

        $app_config = (object) AppConfig::get_config();

        if( !isset( $app_config->key ) || empty( $app_config->key )  || 
            !isset( $payload->data ) || empty( $payload->data ) ||
            !isset( $payload->to ) || empty( $payload->to )
        ){
            return __( 'Missing Configuration!', 'ultimate-push-notifications' );
        }

        $response = wp_remote_post( $this->fcm_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'key=' . $app_config->key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'notification' => $payload->data,
                'to' => $payload->to,
            )),
            'cookies' => array()
            )
        );

        $response = json_decode($response["body"]);

        if( ! \is_object( $response ) ){
            return __( 'Something went wrong! Please check your configuration correctly.', 'ultimate-push-notifications' );
        }

        return array(
            'success' => $response->success,
            'failure' => $response->failure
        );

    }


}

}