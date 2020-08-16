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
		public function save( $user_query ) {
			global $wpdb;

			$user_notifications  = Util::check_evil_script( $user_query['cs_set_notifications'] );
			$get_current_user_id = Util::cs_current_user_id();
			$is_row_exists       = $wpdb->get_var(
				$wpdb->prepare(
					"select id from `{$wpdb->prefix}upn_notifications` where user_id = %d ",
					$get_current_user_id
				)
			);

			// set checkbox val
			if ( $user_notifications ) {
				foreach ( $user_notifications as $key => $val ) {
					if ( false !== \strpos( $key, 'Check' ) ) {
						$user_notifications[ $key ] = 1;
					}
				}
			}

			if ( $is_row_exists ) {
				$wpdb->update(
					"{$wpdb->prefix}upn_notifications",
					array(
						'notification_type' => maybe_serialize( $user_notifications ),
					),
					array(
						'id'      => $is_row_exists,
						'user_id' => $get_current_user_id,
					)
				);
				$resMsg = 'updated';
			} else {
				$wpdb->insert(
					"{$wpdb->prefix}upn_notifications",
					array(
						'user_id'           => $get_current_user_id,
						'notification_type' => maybe_serialize( $user_notifications ),
					)
				);
				$resMsg = 'saved';
			}

			return wp_send_json(
				array(
					'status' => true,
					'title'  => 'Success!',
					'text'   => __( "Thank you! Notification settings {$resMsg} successfully.", 'ultimate-push-notifications' ),
				)
			);

		}

		/**
		 * Get App Configuration
		 *
		 * @return void
		 */
		public static function get_notification_type( $user_id = false ) {
			global $wpdb;
			$get_current_user_id = false === $user_id ? Util::cs_current_user_id() : $user_id;
			$get_row             = $wpdb->get_row(
				$wpdb->prepare(
					"select * from `{$wpdb->prefix}upn_notifications` where user_id = %d ",
					$get_current_user_id
				)
			);

			return isset( $get_row->notification_type ) ? maybe_unserialize( $get_row->notification_type ) : '';
		}

		/**
		 * Get notification type
		 * for multi users
		 *
		 * @return void
		 */
		public static function has_user_asked_for_notification( $user_id, $notification_type ) {
			if ( empty( $user_id ) ) {
				return false;
			}

			$get_user_notification_settings = self::get_notification_type( $user_id );

			if ( empty( $get_user_notification_settings ) ) {
				return false;
			}
			$get_user_notification_settings = (object) $get_user_notification_settings;

			$hasAsked = $notification_type . 'Check';
			if ( isset( $get_user_notification_settings->{$hasAsked} ) ) {

				// check has custom title & descriptions
				$titleText = $notification_type . 'Title';
				$title     = isset( $get_user_notification_settings->{$titleText} ) ? $get_user_notification_settings->{$titleText} : '';

				$bodyText = $notification_type . 'Body';
				$body     = isset( $get_user_notification_settings->{$bodyText} ) ? $get_user_notification_settings->{$bodyText} : '';

				// get user tokens
				global $wpdb;
				$tokens = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT token from {$wpdb->prefix}upn_user_devices where user_id = %d ",
						$user_id
					)
				);

				return (object) array(
					'title'  => $title,
					'body'   => $body,
					'tokens' => $tokens,
				);

			}

			return false;

		}


	}

}

