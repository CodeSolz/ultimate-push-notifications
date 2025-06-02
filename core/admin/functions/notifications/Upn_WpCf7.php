<?php namespace UltimatePushNotifications\admin\functions\notifications;

/**
 * Contact Form 7 Notifications
 *
 * @package Notifications
 * @since 1.1.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\admin\options\functions\SetNotifications;

class Upn_WpCf7 {

	/**
	 * Send notification when form submitted
	 *
	 * @param [type] $contact_form
	 * @param [type] $abort
	 * @param [type] $submission
	 * @return void
	 */
	public static function upn_on_form_submission( $contact_form, $abort, $submission  ) {

		$admin_ids = Util::get_all_super_admins();
		if( $admin_ids ){
			$submitted_data = $submission->get_posted_data();
			foreach( $admin_ids as $admin_id ){
				$hasUserAsked = SetNotifications::has_user_asked_for_notification( $admin_id, 'wpcf7FormSubmission' );
				if( $hasUserAsked ){
					self::upn_notification_on_form_submission( $hasUserAsked, $submitted_data );
				}
			}
		}

		return;
	}


	/**
	 * Send notification when form submitted
	 *
	 * @param [type] $submitted_data
	 * @return void
	 */
	private static function upn_notification_on_form_submission( $hasUserAsked, $submitted_data){

		$find = array(
			'{user_name}',
			'{user_email}',
			'{msg_subject}',
			'{msg_body}',
		);

		$replace = array(
			isset($submitted_data['your-name']) ? $submitted_data['your-name'] : '',
			isset($submitted_data['your-email']) ? $submitted_data['your-email'] : '',
			isset($submitted_data['your-subject']) ? $submitted_data['your-subject'] : '',
			isset($submitted_data['your-message']) ? $submitted_data['your-message'] : '',
		);

		// get sender avatar
		$user_avatar = '';

		return SendNotifications::prepare_send_notifications(
			(array) $hasUserAsked + array(
				'find'         => $find,
				'replace'      => $replace,
				'icon'         => $user_avatar,
				'click_action' => ''
			)
		);

	}


	

}
