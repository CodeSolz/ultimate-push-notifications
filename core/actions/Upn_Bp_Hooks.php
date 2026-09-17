<?php namespace UltimatePushNotifications\actions;

/**
 * BuddyPress: the "Push Notifications" tab in a member's settings.
 *
 * The BuddyPress event notifications themselves (friend requests, messages,
 * activity, groups) are automation rules now — see automation/triggers.
 *
 * @package Action
 * @since 1.0.0
 * @since 1.6.0 Only the settings tab remains here.
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\front\bp\BpSettingsTpl;

class Upn_Bp_Hooks {

	function __construct() {
		add_action( 'bp_setup_nav', array( $this, 'register_tab' ), 100 );
	}

	/**
	 * Add the tab under Notifications for the member viewing their own profile.
	 *
	 * @return void
	 */
	public function register_tab() {
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'notifications' ) || ! function_exists( 'bp_core_new_subnav_item' ) ) {
			return;
		}

		$slug = function_exists( 'bp_get_notifications_slug' ) ? bp_get_notifications_slug() : 'notifications';
		$user = function_exists( 'bp_displayed_user_domain' ) ? bp_displayed_user_domain() : '';
		if ( '' === $user ) {
			return;
		}

		bp_core_new_subnav_item(
			array(
				'name'            => __( 'Push Notifications', 'ultimate-push-notifications' ),
				'slug'            => 'push-notifications',
				'parent_url'      => trailingslashit( $user . $slug ),
				'parent_slug'     => $slug,
				'screen_function' => array( $this, 'screen' ),
				'position'        => 40,
				'user_has_access' => function_exists( 'bp_is_my_profile' ) ? bp_is_my_profile() : false,
				'site_admin_only' => false,
			)
		);
	}

	/**
	 * @return void
	 */
	public function screen() {
		new BpSettingsTpl();
	}

}
