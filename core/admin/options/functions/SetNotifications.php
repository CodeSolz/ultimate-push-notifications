<?php namespace UltimatePushNotifications\admin\options\functions;

/**
 * Database Actions handler for 
 * Set Notifications
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;

if ( ! \class_exists( 'SetNotifications' ) ) {

	class SetNotifications {

		/**
		 * Save App Config
		 *
		 * @return void
		 */
		public function save( $user_query ){
			global $wpdb;

			$user_notifications = Util::check_evil_script( $user_query['cs_set_notifications'] );
			$get_current_user_id = get_current_user_id();
			$is_row_exists = $wpdb->get_var( $wpdb->prepare(
				"select id from `{$wpdb->prefix}upn_notifications` where user_id = %d ", $get_current_user_id
			));

			
			//set checkbox val
			if( $user_notifications ){
				foreach( $user_notifications as $key => $val ){
					if( false !== \strpos( $key, 'Check') ){
						$user_notifications[ $key ] = 1;
					}
				}
			}
			
			if( $is_row_exists ) {
				$wpdb->update(
					"{$wpdb->prefix}upn_notifications",
					array(
						'notification_type' => maybe_serialize( $user_notifications )
					),
					array(
						'id' => $is_row_exists,
						'user_id' => $get_current_user_id
					)
				);
				$resMsg = 'updated';
			}else{
				$wpdb->insert(
					"{$wpdb->prefix}upn_notifications",
					array(
						'user_id' => $get_current_user_id,
						'notification_type' => maybe_serialize( $user_notifications )
					)
				);
				$resMsg = 'saved';
			}

			return wp_send_json(
				array(
					'status'       => true,
					'title'        => 'Success!',
					'text'         => __( "Thank you! Notification settings {$resMsg} successfully.", 'ultimate-push-notifications' )
				)
			);

		}

		/**
		 * Get App Configuration
		 *
		 * @return void
		 */
		public static function get_notification_type( $user_id = false ){
			global $wpdb;
			$get_current_user_id = false === $user_id ? get_current_user_id() : $user_id;
			$get_row = $wpdb->get_row(
				$wpdb->prepare(
					"select * from `{$wpdb->prefix}upn_notifications` where user_id = %d ", $get_current_user_id
				)
			);

			return maybe_unserialize( $get_row->notification_type );
		}


	}

}

