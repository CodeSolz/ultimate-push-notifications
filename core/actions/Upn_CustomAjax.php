<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Custom ajax call
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

if ( ! \class_exists( 'Upn_CustomAjax' ) ) {

	class Upn_CustomAjax {

		function __construct() {
			add_action( 'wp_ajax_upn_ajax', array( $this, 'upn_ajax' ) );
			add_action( 'wp_ajax_nopriv_upn_ajax', array( $this, 'upn_ajax' ) );
		}


		/**
		 * custom ajax call
		 */
		public function upn_ajax() {

			if ( ! isset( $_REQUEST['cs_token'] ) || false === check_ajax_referer( SECURE_AUTH_SALT, 'cs_token', false ) ) {
				wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Invalid token', 'ultimate-push-notifications' ),
						'text'   => __( 'Sorry! we are unable recognize your auth!', 'ultimate-push-notifications' ),
					)
				);
			}

			if ( ! isset( $_REQUEST['data'] ) && isset( $_POST['method'] ) ) {
				$data = $_POST;
			} else {
				$data = $_REQUEST['data'];
			}

			if ( empty( $method = $data['method'] ) || strpos( $method, '@' ) === false ) {
				wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Invalid Request', 'ultimate-push-notifications' ),
						'text'   => __( 'Method parameter missing / invalid!', 'ultimate-push-notifications' ),
					)
				);
			}
			$method     = explode( '@', $method );
			$class_path = str_replace( '\\\\', '\\', '\\UltimatePushNotifications\\' . $method[0] );
			if ( ! class_exists( $class_path ) ) {
				wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Invalid Library', 'ultimate-push-notifications' ),
						'text'   => sprintf( __( 'Library Class "%s" not found! ', 'ultimate-push-notifications' ), $class_path ),
					)
				);
			}

			if ( ! method_exists( $class_path, $method[1] ) ) {
				wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Invalid Method', 'ultimate-push-notifications' ),
						'text'   => sprintf( __( 'Method "%1$s" not found in Class "%2$s"! ', 'ultimate-push-notifications' ), $method[1], $class_path ),
					)
				);
			}

			echo ( new $class_path() )->{$method[1]}( $data );
			exit;
		}

	}

}
