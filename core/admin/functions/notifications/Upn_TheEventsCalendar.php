<?php namespace UltimatePushNotifications\admin\functions\notifications;

/**
 * The Events Calendar Notifications
 *
 * @package Notifications
 * @since 1.1.3
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\admin\options\functions\SetNotifications;

class Upn_TheEventsCalendar {

	public static function upn_on_event(){
		
		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $friendship_friend_user_id, 'bpFriendRequest' );

		if ( $hasUserAsked ) {

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $friendship_initiator_user_id ) ) ? $user->display_name : $user_display_name,
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
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $friendship_initiator_user_id ) ) ? $user->display_name : $user_display_name,
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
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_friend_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $friendship_friend_user_id ) ) ? $user->display_name : $user_display_name,
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
	public static function upn_on_friend_rejected( $friendship_id, $friendship ) {

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $friendship->initiator_user_id, 'bpFriendRequestRejected' );

		if ( $hasUserAsked ) {
			$friendship_friend_user_id = $friendship->friend_user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_friend_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $friendship_friend_user_id ) ) ? $user->display_name : $user_display_name,
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
	 * Send push notification on friend request cancel
	 *
	 * @param [type] $friendship
	 * @return void
	 */
	public static function upn_on_friend_request_cancel( $friendship_id, $friendship ) {

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $friendship->friend_user_id, 'bpFriendRequestCancelled' );

		if ( $hasUserAsked ) {
			$friendship_initiator_user_id = $friendship->initiator_user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
			);

			$user = \get_user_by( 'id', $friendship_initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $friendship_initiator_user_id ) ) ? $user->display_name : $user_display_name,
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
					'click_action' => \bp_core_get_user_domain( $friendship_initiator_user_id ),
				)
			);

		}

		return;
	}

	/**
	 * Send push notification on user activity
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public static function upn_activity_posted_update($content, $user_id, $activity_id ) {

		$current_user_id = Util::cs_current_user_id();
		if( $current_user_id == $user_id ){
			return true;
		}

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $current_user_id, 'bpActivityPostPublish' );

		if ( $hasUserAsked ) {
			$initiator_user_id = $user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
				'{activity_post}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name,
				\substr( $content, 0, 15 ) . '...'
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}


	/**
	 * Send push notification on user activity
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public static function upn_activity_post_type_published( $activity_id, $post, $activity_args ) {

		$current_user_id = Util::cs_current_user_id();
		
		if( !isset( $post->post_author ) || empty( $user_id = $post->post_author ) ){
			return true;
		}

		if( $current_user_id == $user_id ){
			return true;
		}

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $current_user_id, 'bpActivityPostTypePublish' );

		if ( $hasUserAsked ) {
			$initiator_user_id = $user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}


	/**
	 * Send push notification on user activity
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public static function upn_activity_post_type_updated( $post, $activity, $activity_post_object ) {

		$current_user_id = Util::cs_current_user_id();
		
		if( !isset( $post->post_author ) || empty( $user_id = $post->post_author ) ){
			return true;
		}

		if( $current_user_id == $user_id ){
			return true;
		}

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $current_user_id, 'bpActivityPostTypeUpdated' );

		if ( $hasUserAsked ) {
			$initiator_user_id = $user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}

	/**
	 * Send push notification on user activity
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public static function upn_activity_post_type_unpublished( $delete_activity_args, $post, $deleted ) {

		$current_user_id = Util::cs_current_user_id();
		
		if( !isset( $post->post_author ) || empty( $user_id = $post->post_author ) ){
			return true;
		}

		if( $current_user_id == $user_id ){
			return true;
		}

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $current_user_id, 'bpActivityPostTypeUnpublished' );

		if ( $hasUserAsked ) {
			$initiator_user_id = $user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}


	/**
	 * Send push notification on user activity
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public static function upn_activity_sent_reply_to_update_notification( $activity, $comment_id, $commenter_id ) {

		$activity_user_id = $activity->user_id;
		if( !isset( $commenter_id ) || empty( $user_id = $commenter_id ) ){
			return true;
		}

		if( $activity_user_id == $user_id ){
			return true;
		}

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $activity_user_id, 'bpActivityPostComment' );

		if ( $hasUserAsked ) {
			$initiator_user_id = $user_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}


	

	/**
	 * Send push notification on message
	 *
	 * @param [type] $message
	 * @return void
	 */
	public static function upn_messages_message_sent( $message ) {

		if ( ! empty( $message->recipients ) ) {
			foreach ( (array) $message->recipients as $recipient ) {
				self::upn_message_sent( $message->sender_id, $recipient->user_id, $message->subject, $message->message );
			}
		}

		return;
	}

	/**
	 * Send notification on message
	 *
	 * @param [type] $sender_id
	 * @param [type] $receiver_id
	 * @param [type] $subject
	 * @param [type] $message
	 * @return void
	 */
	private static function upn_message_sent( $sender_id, $receiver_id, $subject, $message ){

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $receiver_id, 'bpNewMessageReceive' );

		if ( $hasUserAsked ) {
			$initiator_user_id = $sender_id;

			$find = array(
				'{user_full_name}',
				'{user_first_name}',
				'{user_last_name}',
				'{user_display_name}',
				'{msg_title}',
				'{message}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name,
				\substr( $subject, 0, 15 ) .'...',
				\substr( $message, 0, 25 ) .'...'
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => true,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}


	/**
	 * BuddyPress front notifications settings
	 *
	 * @return void
	 */
	public static function upn_bp_front_notifications_settings() {
		if ( isset( $_POST ) && ! empty( $_POST ) ) {
			$user_notifications = Util::check_evil_script( $_POST );
			$res                = ( new SetNotifications() )->save( $user_notifications );
			?>
			<aside class="bp-feedback bp-messages bp-template-notice success upn-bp-success">
				<span class="bp-icon" aria-hidden="true"></span>
				<p><?php echo $res; ?></p>
			</aside>
			<?php
		}
		return true;
	}
	
	/**
	 * Send notification on group invitation 
	 *
	 * @param [type] $to
	 * @param [type] $args
	 * @return void
	 */
	public static function upn_group_invitation_notification( $to, $args ) {

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $to, 'bpGroupsInvitation' );

		if ( $hasUserAsked ) {
			if( !isset($args['tokens']['inviter.id']) || empty( $initiator_user_id = $args['tokens']['inviter.id']) ){
				return;
			}

			$find = array(
				'{sender_full_name}',
				'{sender_first_name}',
				'{sender_last_name}',
				'{sender_display_name}',
				'{group_name}',
				'{inviter_name}',
				'{message}'
			);

			$user = \get_user_by( 'id', $initiator_user_id );

			$replace = array(
				empty( $user->first_name ) ? $user->display_name : $user->first_name . ' ' . $user->last_name,
				empty( $user->first_name ) ? $user->display_name : $user->first_name,
				empty( $user->last_name ) ? $user->display_name : $user->last_name,
				empty( $user_display_name = \bp_core_get_user_displayname( $initiator_user_id ) ) ? $user->display_name : $user_display_name,
				$args['tokens']['group.name'],
				$args['tokens']['inviter.name'],
				$args['tokens']['invite.message'],
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}

	/**
	 * Send notification on group details updated 
	 *
	 * @param [type] $to
	 * @param [type] $args
	 * @return void
	 */
	public static function upn_group_details_updated( $args ) {

		if( ! isset( $args['tokens']['group.id']) || empty( $group_id = $args['tokens']['group.id'] ) ){
			return;
		}

		$group_member = (int) bp_group_has_members( $group_id );

		if( empty( $group_member ) ){
			return;
		}

		$hasUserAsked = SetNotifications::has_user_asked_for_notification( $group_member, 'bpGroupDetailsUpdated' );

		if ( $hasUserAsked ) {

			$find = array(
				'{group_name}',
				'{changed_text}'
			);

			$replace = array(
				$args['tokens']['group.name'],
				$args['tokens']['changed_text']
			);

			// get sender avatar
			$user_avatar = \bp_core_fetch_avatar(
				array(
					'html'    => false,
					'item_id' => $initiator_user_id,
				)
			);

			return SendNotifications::prepare_send_notifications(
				(array) $hasUserAsked + array(
					'find'         => $find,
					'replace'      => $replace,
					'icon'         => $user_avatar,
					'click_action' => \bp_core_get_user_domain( $initiator_user_id ),
				)
			);

		}

		return;
	}

}
