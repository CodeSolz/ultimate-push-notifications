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
use UltimatePushNotifications\admin\functions\notifications\Upn_BuddyPress;

class Upn_Bp_Hooks {

	function __construct() {
		add_action( 'friends_friendship_requested', array( $this, 'upn_on_friend_request' ), 20, 4 );
		add_action( 'friends_friendship_accepted', array( $this, 'upn_on_friend_accepted' ) );
		add_action( 'friends_friendship_rejected', array( $this, 'upn_on_friend_rejected' ) );
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

	


}

