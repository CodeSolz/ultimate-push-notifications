<?php namespace UltimatePushNotifications\lib;

/**
 * Util Functions
 *
 * @package Library
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.com>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}


class Util {

	/**
	 * Encode Html Entites
	 *
	 * @param type $str
	 * @return type
	 */
	public static function encode_html_chars( $str ) {
		return \esc_html( $str );
	}

	/**
	 * markup tagline
	 *
	 * @param type $tagline
	 */
	public static function markup_tag( $tagline ) {
			echo sprintf( "\n<!--%s - %s-->\n", CS_UPN_PLUGIN_NAME, $tagline );
	}

	/**
	 * Check Evil Script Into User Input
	 *
	 * @param array|string $user_input
	 * @return type
	 */
	public static function check_evil_script( $user_input, $textarea = false ) {
		if ( is_array( $user_input ) ) {
			$user_input = self::cs_sanitize_recursive( $user_input, $textarea );
		} else {
			$user_input = self::cs_sanitize_field( $user_input, $textarea );
		}
		return $user_input;
	}

	/**
	 * Sanitize recursive array
	 *
	 * @param [type]  $user_input
	 * @param boolean $textarea
	 * @return void
	 */
	public static function cs_sanitize_recursive( $user_input, $textarea = false ) {
		foreach ( $user_input as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::cs_sanitize_recursive( $value, $textarea );
			} else {
				$value = self::cs_sanitize_field( $value, $textarea );
			}
		}

		return $user_input;
	}

	/**
	 * Sanitize field
	 *
	 * @param [type] $user_input
	 * @param [type] $textarea
	 * @return void
	 */
	public static function cs_sanitize_field( $user_input, $textarea = false ) {
		if ( $textarea === true ) {
			$user_input = \sanitize_textarea_field( $user_input );
		} else {
			$user_input = \sanitize_text_field( $user_input );
		}
		return self::cs_stripslashes( $user_input );
	}

	/**
	 * Add slashes
	 *
	 * @param [type] $value
	 * @return void
	 */
	public static function cs_addslashes( $value ) {
		return \wp_slash( \stripslashes_deep( trim( $value ) ) );
	}

	/**
	 * Strip slashes
	 *
	 * @param [type] $value
	 * @return void
	 */
	public static function cs_esc_html( $value ) {
		return \esc_html( \stripslashes_deep( trim( $value ) ) );
	}

	/**
	 * Strip slashes
	 *
	 * @param [type] $value
	 * @return void
	 */
	public static function cs_stripslashes( $value ) {
		return \stripslashes_deep( trim( $value ) );
	}

	/**
	 * generate admin page url
	 *
	 * @return string
	 */
	public static function cs_generate_admin_url( $page_name ) {
		if ( empty( $page_name ) ) {
			return '';
		}

		return \admin_url( "admin.php?page={$page_name}" );
	}

	/**
	 * Get back to link / button
	 */
	public static function generate_back_btn( $back_to, $class ) {
		$back_url = self::cs_generate_admin_url( $back_to );
		return "<a href='{$back_url}' class='{$class}'>" . __( '<< Back', 'ultimate-push-notifications' ) . '</a>';
	}


	/**
	 * Get current user id
	 *
	 * @return void
	 */
	public static function cs_current_user_id() {
		global $current_user;
		\wp_get_current_user();
		return $current_user->ID;
	}

	/**
	 * Get all super admins
	 *
	 * @return void
	 */
	public static function get_all_super_admins(){
		$super_admins = \get_super_admins();
		if( $super_admins ){
			$admin_ids = [];
			foreach ($super_admins as $login) {
				$user = \get_user_by( 'login', $login);
				if( isset($user->ID) && !empty($user->ID) ){
					$admin_ids[] = $user->ID;
				}
			}
			return $admin_ids;
		}

		return false;
	}

	/**
	 * Create file
	 *
	 * @param [type] $path
	 * @param [type] $content
	 * @return void
	 */
	public static function create_file( $path, $content ) {
		if ( \file_exists( $path ) ) {
			\unlink( $path );
		}
		\file_put_contents( $path, $content );

		return true;
	}

	/**
	 * Get current url slugs
	 *
	 * @return void
	 */
	public static function current_url_slugs() {
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		return empty( $current_url ) ? '' : \explode( '/', $current_url );
	}

	/**
	 * App config notification
	 *
	 * @return void
	 */
	public static function upn_no_app_config_notification( $option ){
		if( isset( $option['hasConfigSetup'] ) && false === $option['hasConfigSetup'] ){
			return '<div class="badge-error">Please set "App Config" to make this plugin works.</div>';
		}

		return false;
	}

	/**
	 * Free plugins
	 *
	 * @return void
	 */
	public static function cs_free_plugins() {
		return \self_admin_url( 'plugin-install.php?s=codesolz&tab=search&type=author' );
	}

	/**
	 * Nav Caps
	 *
	 * @return String or array
	 */
	public static function upn_nav_cap( $cap_key = ""  ){
		$nav_caps = \apply_filters( "bfrp_nav_caps", array(
			'menu_app_config'  => 'upn_menu_app_config',
			'menu_set_notifications' => 'upn_menu_set_notifications',
			'menu_add_my_device'     => 'upn_menu_add_my_device',
			'menu_all_registered_devices'     => 'upn_menu_all_registered_devices'
		));

		return !empty( $cap_key ) && isset( $nav_caps[$cap_key] ) ? $nav_caps[$cap_key] : $nav_caps;
	}

}
