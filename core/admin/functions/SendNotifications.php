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


class SendNotifications {

	private $fcm_url = 'https://fcm.googleapis.com/fcm/send';

	/**
	 * FCM error codes that indicate the device token is permanently invalid.
	 * Tokens with these errors are removed automatically to keep the list clean.
	 */
	private $invalid_token_errors = array( 'NotRegistered', 'InvalidRegistration' );

	/**
	 * Send Test Notifications
	 *
	 * @return void
	 */
	public function send_test_notifications( $user_input ) {

		global $current_user;
		wp_get_current_user();

		$args = (object) array(
			'to'   => Util::check_evil_script( $user_input['device_token'] ),
			'data' => array(
				'title'        => __( 'Ultimate Push Notification', 'ultimate-push-notifications' ),
				'body'         => sprintf( __( "Hi %s, I'm Ultimate Push Notifications. Hope you will enjoy it!", 'ultimate-push-notifications' ), $current_user->user_login ),
				'icon'         => CS_UPN_PLUGIN_ASSET_URI . 'img/icon-push.png',
				'click_action' => site_url(),
			),
		);

		$response = $this->send_notification( $args );

		if ( isset( $response['success'] ) && $response['success'] > 0 ) {
			return wp_send_json(
				array(
					'status' => true,
					'title'  => __( 'Success!', 'ultimate-push-notifications' ),
					'text'   => __( 'Notification sent successfully.', 'ultimate-push-notifications' ),
				)
			);
		} elseif ( isset( $response['failure'] ) && $response['failure'] > 0 ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Failure!', 'ultimate-push-notifications' ),
					'text'   => sprintf( __( 'Error : %s. Please check your application configuration properly.', 'ultimate-push-notifications' ), $response['errorText'] ),
				)
			);
		} else {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error!', 'ultimate-push-notifications' ),
					'text'   => $response,
				)
			);
		}

	}

	/**
	 * Prepare and send notifications to all provided device tokens.
	 *
	 * @param array|object $dataObj Notification data: title, body, icon, image, click_action, find, replace, tokens
	 * @return array
	 */
	public static function prepare_send_notifications( $dataObj ) {
		$dataObj = \is_object( $dataObj ) ? $dataObj : (object) $dataObj;

		$title       = \str_replace( $dataObj->find, $dataObj->replace, $dataObj->title );
		$description = \str_replace( $dataObj->find, $dataObj->replace, $dataObj->body );
		$response    = array();
		if ( ! empty( $dataObj->tokens ) ) {
			foreach ( $dataObj->tokens as $item ) {
				$payload = (object) array(
					'to'   => $item->token,
					'data' => array(
						'title'        => $title,
						'body'         => $description,
						'icon'         => isset( $dataObj->icon ) ? $dataObj->icon : '',
						'image'        => isset( $dataObj->image ) ? $dataObj->image : '',
						'click_action' => isset( $dataObj->click_action ) ? $dataObj->click_action : site_url(),
					),
				);

				$response[] = ( new self() )->send_notification( $payload );
			}
		}

		return $response;
	}

	/**
	 * Send a single push notification via FCM Legacy HTTP API.
	 *
	 * @param object $payload { to, data: { title, body, icon, image, click_action } }
	 * @return array|string
	 */
	private function send_notification( $payload ) {

		$app_config = (object) AppConfig::get_config();

		if ( ! isset( $app_config->key ) || empty( $app_config->key ) ||
		! isset( $payload->data ) || empty( $payload->data ) ||
		! isset( $payload->to ) || empty( $payload->to )
		) {
			return __( 'Missing Configuration!', 'ultimate-push-notifications' );
		}

		$body = array(
			'data' => $payload->data,
			'to'   => $payload->to,
		);

		// Also send standard notification block for better delivery on some devices
		$body['notification'] = array(
			'title' => isset( $payload->data['title'] ) ? $payload->data['title'] : '',
			'body'  => isset( $payload->data['body'] ) ? $payload->data['body'] : '',
			'icon'  => isset( $payload->data['icon'] ) ? $payload->data['icon'] : '',
		);
		if ( ! empty( $payload->data['image'] ) ) {
			$body['notification']['image'] = $payload->data['image'];
		}

		$http_response = wp_remote_post(
			$this->fcm_url,
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Authorization' => 'key=' . $app_config->key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'cookies'     => array(),
			)
		);

		if ( is_wp_error( $http_response ) ) {
			return __( 'HTTP request failed! Please check your server connection.', 'ultimate-push-notifications' );
		}

		$response = json_decode( wp_remote_retrieve_body( $http_response ) );

		if ( ! \is_object( $response ) ) {
			return __( 'Something went wrong! Please check your configuration correctly.', 'ultimate-push-notifications' );
		}

		$error_text = isset( $response->results[0]->error ) ? $response->results[0]->error : '';

		$final_res = array(
			'success'   => isset( $response->success ) ? (int) $response->success : 0,
			'failure'   => isset( $response->failure ) ? (int) $response->failure : 1,
			'errorText' => $error_text ? $error_text : 'Unknown error',
		);

		$this->update_message_sent_count( $final_res, $payload->to );

		// Auto-remove permanently invalid tokens to keep the device list clean
		if ( $final_res['failure'] > 0 && in_array( $error_text, $this->invalid_token_errors, true ) ) {
			$this->remove_invalid_token( $payload->to );
		}

		return $final_res;
	}

	/**
	 * Update notification sent counters for a token.
	 *
	 * @param array  $res   { success, failure }
	 * @param string $token FCM device token
	 * @return bool
	 */
	private function update_message_sent_count( $res, $token ) {
		global $wpdb;

		$token_short_arr = \explode( ':', $token );
		if ( isset( $token_short_arr[0] ) && empty( $token_short = $token_short_arr[0] ) ) {
			return false;
		}

		$is_exists = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}upn_user_devices` WHERE token LIKE %s ",
				'%' . $wpdb->esc_like( $token_short ) . '%'
			)
		);

		if ( $is_exists ) {
			$wpdb->update(
				"{$wpdb->prefix}upn_user_devices",
				array(
					'total_sent_success_notifications' => $is_exists->total_sent_success_notifications + $res['success'],
					'total_sent_fail_notifications'    => $is_exists->total_sent_fail_notifications + $res['failure'],
				),
				array( 'id' => $is_exists->id )
			);
		}
		return true;
	}

	/**
	 * Remove a permanently invalid token from the database.
	 * Called automatically when FCM returns NotRegistered or InvalidRegistration.
	 *
	 * @param string $token Full FCM device token
	 * @return void
	 */
	private function remove_invalid_token( $token ) {
		global $wpdb;

		$token_short_arr = \explode( ':', $token );
		$token_short     = isset( $token_short_arr[0] ) ? $token_short_arr[0] : $token;

		if ( empty( $token_short ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}upn_user_devices` WHERE token LIKE %s",
				'%' . $wpdb->esc_like( $token_short ) . '%'
			)
		);
	}


}


