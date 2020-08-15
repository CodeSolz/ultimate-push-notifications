<?php namespace UltimatePushNotifications\lib;

/**
 * Util Functions
 *
 * @package Library
 * @since 1.0.0
 * @author CodeSolz <customer-service@codesolz.com>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

if ( ! \class_exists( 'Util' ) ) {

	class Util {

		/**
		 * Encode Html Entites
		 *
		 * @param type $str
		 * @return type
		 */
		public static function encode_html_chars( $str ) {
			return esc_html( $str );
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
				if ( $textarea === true ) {
					$user_input = array_map( 'sanitize_textarea_field', $user_input );
				} else {
					$user_input = array_map( 'sanitize_text_field', $user_input );
				}
				$user_input = array_map( 'stripslashes_deep', $user_input );
			} else {
				if ( $textarea === true ) {
					$user_input = sanitize_textarea_field( $user_input );
				} else {
					$user_input = sanitize_text_field( $user_input );
				}
				$user_input = stripslashes_deep( $user_input );
				$user_input = trim( $user_input );
			}
			return $user_input;
		}

		/**
		 * Add slashes
		 *
		 * @param [type] $value
		 * @return void
		 */
		public static function cs_addslashes( $value ) {
			return wp_slash( stripslashes_deep( trim( $value ) ) );
		}

		/**
		 * Strip slashes
		 *
		 * @param [type] $value
		 * @return void
		 */
		public static function cs_esc_html( $value ) {
			return esc_html( stripslashes_deep( trim( $value ) ) );
		}

		/**
		 * Strip slashes
		 *
		 * @param [type] $value
		 * @return void
		 */
		public static function cs_stripslashes( $value ) {
			return stripslashes_deep( trim( $value ) );
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
	}

}
