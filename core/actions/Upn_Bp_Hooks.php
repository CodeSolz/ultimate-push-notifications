<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Register custom menu
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
		add_action( 'friends_friendship_requested', array( $this, 'upn_on_friend_request' ), 20, 4 );
		add_action( 'friends_friendship_accepted', array( $this, 'upn_on_friend_accepted' ) );
		add_action( 'friends_friendship_rejected', array( $this, 'upn_on_friend_rejected' ) );

		//front-end settings
		add_action( 'wp', array( $this, 'upn_front_settings') );
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
	public function upn_on_friend_rejected( $friendship ) {
		return Upn_BuddyPress::upn_on_friend_rejected( $friendship );
	}


	/**
	 * Front-end settings
	 *
	 * @return void
	 */
	public function upn_front_settings(){
		global $bp;
		// bp_core_new_nav_item( array(
		// 	'name' => __( 'My Links', 'bp-my-links' ),
		// 	'slug' => 'my-link',
		// 	'position' => 80,
		// 	'screen_function' => array( $this, 'bp_my_link_screen_my_links' ),
		// 	'default_subnav_slug' => 'my-link'
		// ) );

		// pre_print( $bp );

		bp_core_new_subnav_item( array(
			'name' => __( 'Push Notifications', 'buddypress' ),
			'slug' => 'push-notifications',
			'parent_url' => trailingslashit( bp_displayed_user_domain() . $bp->notifications->slug ),
			'parent_slug' => $bp->notifications->slug,
			'screen_function' => new BpSettingsTpl(),
			'position' => 40,
			'user_has_access' => current_user_can('edit_posts'),
			'site_admin_only' => false,
			'item_css_id' => $bp->notifications->id
		) );
	}

}

