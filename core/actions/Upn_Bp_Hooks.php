<?php namespace UltimatePushNotifications\actions;

/**
 * Class: BuddyPress Hooks
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\front\bp\BpSettingsTpl;
use UltimatePushNotifications\admin\functions\notifications\Upn_BuddyPress;

class Upn_Bp_Hooks {

	function __construct() {
		//friend
		add_action( 'friends_friendship_requested', array( $this, 'upn_on_friend_request' ), 20, 4 );
		add_action( 'friends_friendship_accepted', array( $this, 'upn_on_friend_accepted' ), 20, 4 );
		add_action( 'friends_friendship_rejected', array( $this, 'upn_on_friend_rejected' ), 20, 2 );
		add_action( 'friends_friendship_withdrawn', array( $this, 'upn_on_friend_request_cancel' ), 20, 2 );

		//activity
		add_action( 'bp_activity_post_type_published', array( $this, 'upn_activity_post_type_published' ), 20, 3 );
		add_action( 'bp_activity_post_type_updated', array( $this, 'upn_activity_post_type_updated' ), 20, 3 );
		add_action( 'bp_activity_post_type_unpublished', array( $this, 'upn_activity_post_type_unpublished' ), 20, 3 );
		add_action( 'bp_activity_posted_update', array( $this, 'upn_activity_posted_update' ), 20, 3 );
		add_action( 'bp_activity_sent_reply_to_update_notification', array( $this, 'upn_activity_sent_reply_to_update_notification' ), 20, 3 );

		//message
		add_action( 'messages_message_sent', array( $this, 'upn_messages_message_sent' ), 15 );

		//groups
		add_action( 'bp_send_email_delivery_class', array( $this, 'upn_group_invitation_notification' ), 15, 4 );
		add_action( 'bp_send_email_delivery_class', array( $this, 'upn_group_details_updated' ), 15, 4 );

		// front-end settings
		add_action( 'wp', array( $this, 'upn_front_settings' ) );
		add_action( 'upn_bp_front_notifications_settings', array( $this, 'upn_bp_front_notifications_settings' ) );
	}


	/**
	 * On friend request
	 *
	 * @param [type] $friendship_id
	 * @param [type] $friendship_initiator_user_id
	 * @param [type] $friendship_friend_user_id
	 * @param [type] $friendship
	 * @return void
	 */
	public function upn_on_friend_request( $friendship_id, $friendship_initiator_user_id, $friendship_friend_user_id, $friendship ) {
		return Upn_BuddyPress::upn_on_friend_request( $friendship_id, $friendship_initiator_user_id, $friendship_friend_user_id, $friendship );
	}

	/**
	 * On friend request accepted
	 *
	 * @param [type] $friendship_id
	 * @param [type] $friendship_initiator_user_id
	 * @param [type] $friendship_friend_user_id
	 * @param [type] $friendship
	 * @return void
	 */
	public function upn_on_friend_accepted( $friendship_id, $friendship_initiator_user_id, $friendship_friend_user_id, $friendship ) {
		return Upn_BuddyPress::upn_on_friend_accepted( $friendship_id, $friendship_initiator_user_id, $friendship_friend_user_id, $friendship );
	}

	/**
	 * On friend request accepted
	 *
	 * @param [type] $friendship
	 * @return void
	 */
	public function upn_on_friend_rejected( $friendship_id, $friendship ) {
		return Upn_BuddyPress::upn_on_friend_rejected( $friendship_id, $friendship );
	}

	/**
	 * On friend request cancelled
	 *
	 * @param [type] $friendship_id
	 * @param [type] $friendship
	 * @return void
	 */
	public function upn_on_friend_request_cancel( $friendship_id, $friendship ) {
		return Upn_BuddyPress::upn_on_friend_request_cancel( $friendship_id, $friendship );
	}

	/**
	 * Activity post published
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public function upn_activity_post_type_published( $activity_id, $post, $activity_args ) {
		return Upn_BuddyPress::upn_activity_post_type_published( $activity_id, $post, $activity_args );
	}

	/**
	 * Activity post updated
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public function upn_activity_post_type_updated( $post, $activity, $activity_post_object ) {
		return Upn_BuddyPress::upn_activity_post_type_updated( $post, $activity, $activity_post_object );
	}

	/**
	 * Activity post unpublished
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public function upn_activity_post_type_unpublished( $delete_activity_args, $post, $deleted ) {
		return Upn_BuddyPress::upn_activity_post_type_unpublished( $delete_activity_args, $post, $deleted );
	}

	/**
	 * Activity post published
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public function upn_activity_posted_update( $content, $user_id, $activity_id ) {
		return Upn_BuddyPress::upn_activity_posted_update( $content, $user_id, $activity_id );
	}


	/**
	 * User Activity
	 *
	 * @param [type] $activity_id
	 * @param [type] $post
	 * @param [type] $activity_args
	 * @return void
	 */
	public function upn_activity_sent_reply_to_update_notification( $activity, $comment_id, $commenter_id ) {
		return Upn_BuddyPress::upn_activity_sent_reply_to_update_notification( $activity, $comment_id, $commenter_id );
	}

	/**
	 * Messages
	 *
	 * @param [type] $message
	 * @return void
	 */
	public function upn_messages_message_sent( $message ) {
		return Upn_BuddyPress::upn_messages_message_sent( $message );
	}


	/**
	 * group invitation
	 *
	 * @param [type] $mailer
	 * @param [type] $email_type
	 * @param [type] $to
	 * @param [type] $args
	 * @return void
	 */
	public function upn_group_invitation_notification( $mailer, $email_type, $to, $args){
		if( $email_type == 'groups-invitation' ){
			Upn_BuddyPress::upn_group_invitation_notification( $to, $args );
		}
		return $mailer;
	}

	/**
	 * Send notification when group details updated
	 *
	 * @param [type] $mailer
	 * @param [type] $email_type
	 * @param [type] $to
	 * @param [type] $args
	 * @return void
	 */
	public function upn_group_details_updated( $mailer, $email_type, $to, $args){
		if( $email_type == 'groups-details-updated' ){
			Upn_BuddyPress::upn_group_details_updated( $args );
		}
		return $mailer;
	}

	/**
	 * Front-end settings
	 *
	 * @return void
	 */
	public function upn_front_settings() {
		$url_slug = Util::current_url_slugs();
		if ( isset( $url_slug[2] ) && ! empty( $url_slug[2] ) &&
		( isset( $url_slug['3'] ) && $url_slug['3'] == 'notifications'  )
		) {
			global $bp;
			bp_core_new_subnav_item(
				array(
					'name'            => __( 'Push Notifications', 'buddypress' ),
					'slug'            => 'push-notifications',
					'parent_url'      => \trailingslashit( bp_displayed_user_domain() . $bp->notifications->slug ),
					'parent_slug'     => $bp->notifications->slug,
					'screen_function' => new BpSettingsTpl(),
					'position'        => 40,
					'user_has_access' => current_user_can( 'read' ),
					'site_admin_only' => false,
					'item_css_id'     => $bp->notifications->id,
				)
			);
		}

	}

	/**
	 * BuddyPress front-end notifications
	 *
	 * @return void
	 */
	public function upn_bp_front_notifications_settings() {
		return Upn_BuddyPress::upn_bp_front_notifications_settings();
	}

}

