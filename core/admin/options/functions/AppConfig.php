<?php namespace UltimatePushNotifications\admin\options\functions;

/**
 * Database Actions handler for App Config
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\options\functions\firebasejs\FirebaseJs;


class AppConfig {

	/**
	 * Add Configuration key
	 *
	 * @var string
	 */
	private static $app_config_key = 'cs_upn_app_config';

	/**
	 * Save App Config
	 *
	 * @return void
	 */
	public function save( $user_query ) {

		if ( !current_user_can( 'manage_options' ) && !current_user_can( Util::upn_nav_cap('menu_app_config') ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'ultimate-push-notifications' ),
                'text'   => __( 'You do not have permission to perform this action.', 'ultimate-push-notifications' ),
				)
			);
        }

		$user_app_config = Util::check_evil_script( $user_query['cs_app_config'] );

		$measurementId = $user_app_config['measurementId'];
		unset( $user_app_config['measurementId'] );

		// check empty
		$is_empty = false;
		if ( $user_app_config ) {
			foreach ( $user_app_config as  $key => $val ) {
				if ( empty( $val ) ) {
					$is_empty = true;
					break;
				}
			}
		}

		if ( true === $is_empty ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => __( 'One or more field is empty. All fields are required.', 'ultimate-push-notifications' ),
				)
			);
		}

		$user_app_config = array_merge_recursive(
			$user_app_config,
			array(
				'measurementId' => $measurementId,
			)
		);

		update_option( self::$app_config_key, $user_app_config );
		$resMsg = isset( $user_query['cs_app_config_update']['id'] ) ? 'updated' : 'saved';

		Util::create_file(
			CS_UPN_BASE_DIR_PATH . 'assets/plugins/firebase/js/firebaseInit.min.js',
			FirebaseJs::firebase_init( (object) $user_app_config )
		);

		Util::create_file(
			CS_UPN_BASE_DIR_PATH . 'assets/plugins/firebase/js/firebaseMessagingSW.min.js',
			FirebaseJs::firebase_msg_sw( (object) $user_app_config )
		);

		return wp_send_json(
			array(
				'status' => true,
				'title'  => 'Success!',
				'text'   => __( "Thank you! app configuration {$resMsg} successfully.", 'ultimate-push-notifications' ),
			)
		);

	}

	/**
	 * Get App Configuration
	 *
	 * @return void
	 */
	public static function get_config() {
		return get_option( self::$app_config_key );
	}


	/**
	 * Save / update token
	 *
	 * @param [type] $user_input
	 * @return void
	 */
	public function cs_update_token( $user_input ) {
		$current_user = Util::check_evil_script( $user_input['current_user'] );
		if ( empty( $current_user ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => __( 'User need to login to save token.', 'ultimate-push-notifications' ),
				)
			);
		}

		
		global $wpdb;
		$token           = Util::check_evil_script( $user_input['gen_token'] );
		$device_id       = Util::check_evil_script( $user_input['device_id'] );
		$token_short_arr 	= \explode( ':', $token );
		
		if ( isset( $token_short_arr[0] ) && empty( $token_short = $token_short_arr[0] ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => __( 'No device token found!', 'ultimate-push-notifications' ),
				)
			);
		}

		$is_exists = $wpdb->get_var(
			$wpdb->prepare(
				"select id from `{$wpdb->prefix}upn_user_devices` where token like %s and user_id = %d ",
				'%' . $wpdb->esc_like( $token_short ) . '%',
				$current_user
			)
		);

		if ( $is_exists ) {
			$wpdb->update(
				"{$wpdb->prefix}upn_user_devices",
				array(
					'user_id' => $current_user,
					'token'   => $token,
					'device_id'     => $device_id,
				),
				array(
					'id' => $is_exists,
				)
			);
		} else {
			$wpdb->insert(
				"{$wpdb->prefix}upn_user_devices",
				array(
					'user_id'       => $current_user,
					'token'         => $token,
					'device_id'     => $device_id,
					'registered_on' => date( 'Y-m-d H:i:s' ),
				)
			);
		}

		return wp_send_json(
			array(
				'status' => true,
				'title'  => 'Success!',
				'text'   => __( 'Device token saved successfully', 'ultimate-push-notifications' ),
			)
		);

	}


}

