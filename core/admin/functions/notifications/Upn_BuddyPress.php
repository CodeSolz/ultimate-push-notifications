<?php namespace UltimatePushNotifications\admin\functions\notifications;

/**
 * BuddyPress Notifications
 *
 * @package Notifications
 * @since 1.0.6
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\admin\options\functions\SetNotifications;

class Upn_BuddyPress {


	/**
	 * TODO: need to add settings section on user profile area, check user registration working
	 * https://codex.buddypress.org/developer/action-reference/friends/
	 * bp-friends\bp-friends-functions.php
	 */

	/**
	 * Send push notification on friend request
	 *
	 * @param [type] $friendship_id
	 * @param [type] $friendship_initiator_user_id
	 * @param [type] $friendship_friend_user_id
	 * @param [type] $friendship
	 * @return void
	 */
	public static function upn_on_friend_request( $friendship_id, $friendship_initiator_user_id, $friendship_friend_user_id, $friendship ) {

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $friendship_friend_user_id, 'bpFriendRequest' );

		if ( $hasUserAsked ) {

			$find = array(
				'{sender_full_name}',
				'{sender_first_name}',
				'{sender_last_name}',
				'{sender_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_initiator_user_id );

			$replace = array(
				$user->first_name . ' ' . $user->last_name,
				$user->first_name,
				$user->last_name,
				\bp_core_get_user_displayname( $friendship_initiator_user_id ),
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $friendship_initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $friendship_friend_user_id ),
				)
			);

		}

		return;
	}


	/**
	 * Send push notification on friend request accepted
	 *
	 * @param [type] $friendship_id
	 * @param [type] $friendship_initiator_user_id
	 * @param [type] $friendship_friend_user_id
	 * @param [type] $friendship
	 * @return void
	 */
	public static function upn_on_friend_accepted( $friendship_id, $friendship_initiator_user_id, $friendship_friend_user_id, $friendship ) {

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $friendship_initiator_user_id, 'bpFriendRequestAccepted' );

		if ( $hasUserAsked ) {

			$find = array(
				'{sender_full_name}',
				'{sender_first_name}',
				'{sender_last_name}',
				'{sender_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_friend_user_id );

			$replace = array(
				$user->first_name . ' ' . $user->last_name,
				$user->first_name,
				$user->last_name,
				\bp_core_get_user_displayname( $friendship_friend_user_id ),
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $friendship_friend_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $friendship_friend_user_id ),
				)
			);

		}

		return;
	}

	/**
	 * Send push notification on friend request rejected
	 *
	 * @param [type] $friendship
	 * @return void
	 */
	public static function upn_on_friend_rejected( $friendship ) {

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $friendship_initiator_user_id, 'bpFriendRequestRejected' );

		if ( $hasUserAsked ) {

			$find = array(
				'{sender_full_name}',
				'{sender_first_name}',
				'{sender_last_name}',
				'{sender_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_friend_user_id );

			$replace = array(
				$user->first_name . ' ' . $user->last_name,
				$user->first_name,
				$user->last_name,
				\bp_core_get_user_displayname( $friendship_friend_user_id ),
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $friendship_friend_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $friendship_friend_user_id ),
				)
			);

		}

		return;
	}


}
