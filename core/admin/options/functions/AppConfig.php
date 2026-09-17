<?php namespace UltimatePushNotifications\admin\options\functions;

/**
 * Database Actions handler for App Config
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\options\functions\firebasejs\FirebaseJs;
use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\Vapid;


class AppConfig {

	/**
	 * Add Configuration key
	 *
	 * @var string
	 */
	private static $app_config_key = 'cs_upn_app_config';

	/**
	 * Save App Config
	 *
	 * @return void
	 */
	public function save( $user_query ) {

		if ( !current_user_can( 'manage_options' ) && !current_user_can( Util::upn_nav_cap('menu_app_config') ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'ultimate-push-notifications' ),
                'text'   => __( 'You do not have permission to perform this action.', 'ultimate-push-notifications' ),
				)
			);
        }

		$user_app_config = Util::check_evil_script( $user_query['cs_app_config'] );

		// Optional fields — allowed to be empty
		$optional_fields = array( 'measurementId', 'vapidKey' );

		// Check required fields are not empty
		$is_empty = false;
		if ( $user_app_config ) {
			foreach ( $user_app_config as $key => $val ) {
				if ( in_array( $key, $optional_fields, true ) ) {
					continue;
				}
				if ( empty( $val ) ) {
					$is_empty = true;
					break;
				}
			}
		}

		if ( true === $is_empty ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => __( 'One or more required field is empty. Please fill in all required fields.', 'ultimate-push-notifications' ),
				)
			);
		}

		update_option( self::$app_config_key, $user_app_config );
		$resMsg = isset( $user_query['cs_app_config_update']['id'] ) ? 'updated' : 'saved';

		Util::create_file(
			CS_UPN_BASE_DIR_PATH . 'assets/plugins/firebase/js/firebaseInit.min.js',
			FirebaseJs::firebase_init( (object) $user_app_config )
		);

		Util::create_file(
			CS_UPN_BASE_DIR_PATH . 'assets/plugins/firebase/js/firebaseMessagingSW.min.js',
			FirebaseJs::firebase_msg_sw( (object) $user_app_config )
		);

		return wp_send_json(
			array(
				'status' => true,
				'title'  => 'Success!',
				'text'   => __( "Thank you! app configuration {$resMsg} successfully.", 'ultimate-push-notifications' ),
			)
		);

	}

	/**
	 * Get App Configuration
	 *
	 * @return void
	 */
	public static function get_config() {
		return get_option( self::$app_config_key );
	}

	/**
	 * Generate a VAPID key pair (AJAX).
	 *
	 * @param array $user_input force, subject
	 * @return void
	 */
	public function generate_vapid_keys( $user_input ) {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return $this->json( false, __( 'Access Denied', 'ultimate-push-notifications' ), __( 'You do not have permission to perform this action.', 'ultimate-push-notifications' ) );
		}

		$this->save_vapid_subject( isset( $user_input['subject'] ) ? $user_input['subject'] : '' );

		$force  = ! empty( $user_input['force'] ) && '1' === (string) $user_input['force'];
		$result = Vapid::generate_keys( $force );

		if ( \is_wp_error( $result ) ) {
			return $this->json( false, __( 'Could not generate keys', 'ultimate-push-notifications' ), $result->get_error_message() );
		}

		return $this->json(
			true,
			__( 'Web Push is ready', 'ultimate-push-notifications' ),
			__( 'A key pair has been generated. Devices can now register.', 'ultimate-push-notifications' )
		);
	}

	/**
	 * Store a pasted VAPID key pair (AJAX).
	 *
	 * @param array $user_input public_key, private_key, subject
	 * @return void
	 */
	public function save_vapid_keys( $user_input ) {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return $this->json( false, __( 'Access Denied', 'ultimate-push-notifications' ), __( 'You do not have permission to perform this action.', 'ultimate-push-notifications' ) );
		}

		$this->save_vapid_subject( isset( $user_input['subject'] ) ? $user_input['subject'] : '' );

		$public  = isset( $user_input['public_key'] ) ? \preg_replace( '/[^A-Za-z0-9_\-=]/', '', (string) $user_input['public_key'] ) : '';
		$private = isset( $user_input['private_key'] ) ? \preg_replace( '/[^A-Za-z0-9_\-=]/', '', (string) $user_input['private_key'] ) : '';

		$result = Vapid::save_keys( $public, $private );

		if ( \is_wp_error( $result ) ) {
			return $this->json( false, __( 'Keys not saved', 'ultimate-push-notifications' ), $result->get_error_message() );
		}

		return $this->json(
			true,
			__( 'Web Push is ready', 'ultimate-push-notifications' ),
			__( 'The key pair has been saved. Devices can now register.', 'ultimate-push-notifications' )
		);
	}

	/**
	 * Persist the VAPID contact subject if a usable one was supplied.
	 *
	 * @param string $subject
	 * @return void
	 */
	private function save_vapid_subject( $subject ) {
		$subject = \trim( \sanitize_text_field( (string) $subject ) );

		if ( '' === $subject ) {
			return;
		}

		if ( 0 === \strpos( $subject, 'mailto:' ) && \is_email( \substr( $subject, 7 ) ) ) {
			\update_option( Vapid::OPTION_SUBJECT, $subject, false );
		} elseif ( \is_email( $subject ) ) {
			\update_option( Vapid::OPTION_SUBJECT, 'mailto:' . $subject, false );
		} elseif ( 0 === \strpos( $subject, 'https://' ) ) {
			\update_option( Vapid::OPTION_SUBJECT, \esc_url_raw( $subject ), false );
		}

		Vapid::flush_token_cache();
	}

	/**
	 * Emit a JSON response and stop.
	 *
	 * @param bool   $status
	 * @param string $title
	 * @param string $text
	 * @return void
	 */
	private function json( $status, $title, $text ) {
		return wp_send_json(
			array(
				'status' => (bool) $status,
				'title'  => $title,
				'text'   => $text,
			)
		);
	}


	/**
	 * Save / update token
	 *
	 * @param [type] $user_input
	 * @return void
	 */
	public function cs_update_token( $user_input ) {
		/**
		 * The owner of a device token is always the authenticated user making the
		 * request. It is never read from the request body: a client-supplied user id
		 * would let any visitor bind their own device to another account and receive
		 * that account's private notifications.
		 */
		$current_user = \get_current_user_id();
		if ( empty( $current_user ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => __( 'User need to login to save token.', 'ultimate-push-notifications' ),
				)
			);
		}

		$token     = isset( $user_input['gen_token'] ) ? Util::check_evil_script( $user_input['gen_token'] ) : '';
		$device_id = isset( $user_input['device_id'] ) ? Util::check_evil_script( $user_input['device_id'] ) : '';

		// The store matches on the full token, never a prefix — a prefix LIKE
		// could match (and later delete) another user's row.
		$saved = SubscriptionStore::save_fcm_token( $current_user, $token, $device_id );

		if ( \is_wp_error( $saved ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => $saved->get_error_message(),
				)
			);
		}

		return wp_send_json(
			array(
				'status' => true,
				'title'  => 'Success!',
				'text'   => __( 'Device token saved successfully', 'ultimate-push-notifications' ),
			)
		);

	}


}

