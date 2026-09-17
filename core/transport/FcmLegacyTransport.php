<?php namespace UltimatePushNotifications\transport;

use UltimatePushNotifications\admin\options\functions\AppConfig;

/**
 * Legacy Firebase Cloud Messaging transport.
 *
 * Retained so that sites upgrading from 1.4.x keep the behaviour they had, and
 * so their existing device rows remain addressable while they migrate. It is
 * not the default and should not be chosen for a new install.
 *
 * Google shut down this API on 22 July 2024. Sends through it fail for every
 * project created since, and for older projects that have had the legacy API
 * disabled. is_configured() says so explicitly rather than letting the failure
 * surface as an unexplained 401 — the plugin spent two years in exactly that
 * state, and "it just stopped working" is what the support forum filled up with.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class FcmLegacyTransport implements TransportInterface {

	/**
	 * The decommissioned endpoint.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

	/**
	 * Date Google began shutting the legacy API down.
	 *
	 * @var string
	 */
	const SHUTDOWN_DATE = '2024-07-22';

	/**
	 * Errors that mean the registration token is dead.
	 *
	 * @var string[]
	 */
	const GONE_ERRORS = array( 'NotRegistered', 'InvalidRegistration', 'MismatchSenderId' );

	/**
	 * Errors that are transient.
	 *
	 * @var string[]
	 */
	const RETRYABLE_ERRORS = array( 'Unavailable', 'InternalServerError', 'DeviceMessageRateExceeded', 'TopicsMessageRateExceeded' );

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return Subscription::TRANSPORT_FCM_LEGACY;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return \__( 'Firebase Cloud Messaging (legacy)', 'ultimate-push-notifications' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured() {
		$config = AppConfig::get_config();

		if ( empty( $config['key'] ) ) {
			return new \WP_Error(
				'upn_no_server_key',
				\__( 'No Firebase server key has been saved.', 'ultimate-push-notifications' )
			);
		}

		return new \WP_Error(
			'upn_fcm_legacy_retired',
			\sprintf(
				/* translators: %s: shutdown date */
				\__( 'The legacy Firebase Cloud Messaging API was shut down by Google on %s, so notifications sent through it will not be delivered. Switch this site to Web Push on the App Config screen.', 'ultimate-push-notifications' ),
				self::SHUTDOWN_DATE
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( Subscription $subscription ) {
		return Subscription::TRANSPORT_FCM_LEGACY === $subscription->transport;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( Subscription $subscription, array $payload, array $options = array() ) {

		$config = AppConfig::get_config();

		if ( empty( $config['key'] ) ) {
			return SendResult::failure(
				'upn_no_server_key',
				\__( 'No Firebase server key has been saved.', 'ultimate-push-notifications' ),
				0,
				$this->get_id()
			);
		}

		if ( ! $subscription->is_valid() ) {
			return SendResult::gone(
				'upn_incomplete_subscription',
				\__( 'The device record has no registration token.', 'ultimate-push-notifications' ),
				0,
				$this->get_id()
			);
		}

		$body = array(
			'to'   => $subscription->token,
			'data' => $payload,
		);

		if ( isset( $options['ttl'] ) ) {
			$body['time_to_live'] = (int) $options['ttl'];
		}

		$response = \wp_remote_post(
			self::ENDPOINT,
			array(
				'method'      => 'POST',
				'timeout'     => 10,
				'redirection' => 0,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => array(
					'Authorization' => 'key=' . $config['key'],
					'Content-Type'  => 'application/json',
				),
				'body'        => \wp_json_encode( $body ),
				'cookies'     => array(),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return SendResult::from_wp_error( $response, $this->get_id() );
		}

		return $this->interpret_response( $response );
	}

	/**
	 * Turn an FCM response into a SendResult.
	 *
	 * @param array $response
	 * @return SendResult
	 */
	private function interpret_response( $response ) {

		$status = (int) \wp_remote_retrieve_response_code( $response );

		if ( 401 === $status || 403 === $status ) {
			return SendResult::failure(
				'upn_fcm_legacy_retired',
				\sprintf(
					/* translators: %s: shutdown date */
					\__( 'Firebase rejected the server key. The legacy Cloud Messaging API was shut down on %s; this site needs to move to Web Push.', 'ultimate-push-notifications' ),
					self::SHUTDOWN_DATE
				),
				$status,
				$this->get_id()
			);
		}

		if ( 404 === $status ) {
			return SendResult::failure(
				'upn_fcm_legacy_retired',
				\sprintf(
					/* translators: %s: shutdown date */
					\__( 'The legacy Firebase endpoint no longer exists. It was shut down on %s; this site needs to move to Web Push.', 'ultimate-push-notifications' ),
					self::SHUTDOWN_DATE
				),
				$status,
				$this->get_id()
			);
		}

		if ( $status >= 500 ) {
			return SendResult::retry(
				'upn_fcm_server_error',
				\__( 'Firebase returned a server error.', 'ultimate-push-notifications' ),
				$status,
				null,
				$this->get_id()
			);
		}

		$decoded = \json_decode( \wp_remote_retrieve_body( $response ) );

		if ( ! \is_object( $decoded ) ) {
			return SendResult::failure(
				'upn_fcm_bad_response',
				\__( 'Firebase returned a response that could not be read.', 'ultimate-push-notifications' ),
				$status,
				$this->get_id()
			);
		}

		if ( ! empty( $decoded->success ) ) {
			return SendResult::success( $status, $this->get_id() );
		}

		// A per-token error is reported inside results[], not as an HTTP status.
		$error = isset( $decoded->results[0]->error ) ? (string) $decoded->results[0]->error : '';

		if ( \in_array( $error, self::GONE_ERRORS, true ) ) {
			return SendResult::gone(
				'upn_token_invalid',
				\sprintf(
					/* translators: %s: Firebase error code */
					\__( 'Firebase reports this device token is no longer valid (%s).', 'ultimate-push-notifications' ),
					$error
				),
				$status,
				$this->get_id()
			);
		}

		if ( \in_array( $error, self::RETRYABLE_ERRORS, true ) ) {
			return SendResult::retry(
				'upn_fcm_temporary',
				\sprintf(
					/* translators: %s: Firebase error code */
					\__( 'Firebase reported a temporary problem (%s).', 'ultimate-push-notifications' ),
					$error
				),
				$status,
				null,
				$this->get_id()
			);
		}

		return SendResult::failure(
			'upn_fcm_error',
			$error
				? \sprintf(
					/* translators: %s: Firebase error code */
					\__( 'Firebase rejected the notification (%s).', 'ultimate-push-notifications' ),
					$error
				)
				: \__( 'Firebase rejected the notification.', 'ultimate-push-notifications' ),
			$status,
			$this->get_id()
		);
	}

}
