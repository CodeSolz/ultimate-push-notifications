<?php namespace UltimatePushNotifications\actions;

/**
 * Class: WordPress Default Hooks
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\notifications\Upn_UserRegistration;

class Upn_WP_Hooks {

	function __construct() {
		add_action( 'user_register', array( $this, 'upn_on_user_registrations' ) );

		/*** add settings link */
		add_filter( 'plugin_action_links_' . CS_UPN_PLUGIN_IDENTIFIER, array( $this, 'upn_action_links' ) );

		/*** add docs link */
		add_filter( 'plugin_row_meta', array( $this, 'upn_plugin_row_meta' ), 10, 2 );
	}

	/**
	 * When new user get registered
	 *
	 * @param [type] $user_id
	 * @return void
	 */
	public function upn_on_user_registrations( $user_id ) {
		return Upn_UserRegistration::on_user_registration( $user_id );
	}

	/**
	 * Add settings links
	 *
	 * @param [type] $links
	 * @return void
	 */
	public static function upn_action_links( $links ) {
		$custom_links = array(
			'app_config' => '<a href="' . Util::cs_generate_admin_url( 'cs-upn-app-configuration' ) . '">' . __( 'Configuration Settings', 'real-time-auto-find-and-replace' ) . '</a>',
			'set_notification'     => '<a href="' . Util::cs_generate_admin_url( 'cs-upn-set-notifications' ) . '" aria-label="' . esc_attr__( 'set notifications', 'real-time-auto-find-and-replace' ) . '">' . __( 'Notifications Settings', 'real-time-auto-find-and-replace' ) . '</a>',
		);
		

		return array_merge( $custom_links, $links );
	}


	/**
	 * Plugins Row
	 *
	 * @param [type] $links
	 * @param [type] $file
	 * @return void
	 */
	public function upn_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( CS_UPN_PLUGIN_IDENTIFIER ) !== $file ) {
			return $links;
		}

		$row_meta = apply_filters(
			'rtafar_row_meta',
			array(
				'docs'    => '<a target="_blank" href="' . esc_url( 'https://docs.codesolz.net/ultimate-push-notifications/' ) . '" aria-label="' . esc_attr__( 'documentation', 'real-time-auto-find-and-replace' ) . '">' . esc_html__( 'Docs', 'real-time-auto-find-and-replace' ) . '</a>',
				'videos'  => '<a target="_blank" href="' . esc_url( 'https://www.youtube.com/watch?v=TARCZGGlG5k&list=PLxLVEan0phTsg6006fmSx2QHzn28QPkJB' ) . '" aria-label="' . esc_attr__( 'Video Tutorials', 'real-time-auto-find-and-replace' ) . '">' . esc_html__( 'Video Tutorials', 'real-time-auto-find-and-replace' ) . '</a>',
				'support' => '<a target="_blank" href="' . esc_url( 'https://codesolz.net/forum' ) . '" aria-label="' . esc_attr__( 'Community support', 'real-time-auto-find-and-replace' ) . '">' . esc_html__( 'Community support', 'real-time-auto-find-and-replace' ) . '</a>',
			)
		);

		return array_merge( $links, $row_meta );

	}

}

