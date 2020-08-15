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
use UltimatePushNotifications\admin\functions\Masking;

if ( ! \class_exists( 'Upn_WP_Hooks' ) ) {

	class Upn_WP_Hooks {

		function __construct() {
			add_action( 'template_redirect', array( $this, 'rtafar_filter_contents' ) );
		}

		/**
		 * Filter content
		 *
		 * @return void
		 */
		public function rtafar_filter_contents() {
			ob_start(
				array( $this, 'get_filtered_content' )
			);
		}

		/**
		 * Filter content
		 *
		 * @param [type] $buffer
		 * @return void
		 */
		private function get_filtered_content( $buffer ) {
			$replace_rules = Masking::get_rules( 'all' );
			if ( $replace_rules ) {
				foreach ( $replace_rules as $item ) {
					if ( false !== stripos( $item->find, ',' ) ) {
						$finds = explode( ',', $item->find );
						foreach ( $finds as $find ) {
							$buffer = $this->replace( $item, $buffer, $find );
						}
					} else {
						$buffer = $this->replace( $item, $buffer );
					}
				}
			}

			return $buffer;
		}


		/**
		 * Replace
		 *
		 * @param [type]  $item
		 * @param [type]  $buffer
		 * @param boolean $find
		 * @return void
		 */
		private function replace( $item, $buffer, $find = false ) {
			$find = false !== $find ? $find : $item->find;
			if ( $item->type == 'regex' ) {
				$find    = '<' . Util::cs_stripslashes( $find ) . '>';
				$replace = Util::cs_stripslashes( $item->replace );
				return preg_replace( $find, $replace, $buffer );
			} else {
				return str_replace( $find, $item->replace, $buffer );
			}
		}

	}

}
