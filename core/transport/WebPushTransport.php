<?php namespace UltimatePushNotifications\transport;

/**
 * Native Web Push (RFC 8030) with VAPID authentication.
 *
 * The default transport. Sends directly from this server to whichever push
 * service the browser nominated — Google's, Mozilla's, Apple's — with no
 * account, no SDK and no third-party JavaScript involved. The subscription and
 * its keys never leave the site's own database.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class WebPushTransport implements TransportInterface {

	/**
	 * How long the push service should hold an undelivered message, in seconds.
	 *
	 * A day is a deliberate compromise: long enough to survive a closed laptop
	 * overnight, short enough that nobody is woken by an order alert from last
	 * week. Filterable per notification via the ttl option.
	 *
	 * @var int
	 */
	const DEFAULT_TTL = 86400;

	/**
	 * Request timeout in seconds.
	 *
	 * Short by design. Sends run in a queue worker with a wall-clock budget, so
	 * one unresponsive push service must not consume the whole batch.
	 *
	 * @var int
	 */
	const TIMEOUT = 10;

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return Subscription::TRANSPORT_WEBPUSH;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return \__( 'Web Push (VAPID)', 'ultimate-push-notifications' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured() {
		if ( ! Vapid::has_keys() ) {
			return new \WP_Error(
				'upn_no_vapid_keys',
				\__( 'No VAPID key pair has been generated yet. Generate one on the App Config screen.', 'ultimate-push-notifications' )
			);
		}

		if ( ! \function_exists( 'openssl_pkey_derive' ) ) {
			return new \WP_Error(
				'upn_no_pkey_derive',
				\__( 'This server\'s PHP build does not provide openssl_pkey_derive(), which Web Push requires. PHP 7.3 or newer is needed.', 'ultimate-push-notifications' )
			);
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( Subscription $subscription ) {
		return Subscription::TRANSPORT_WEBPUSH === $subscription->transport;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( Subscription $subscription, array $payload, array $options = array() ) {

		$ready = $this->is_configured();
		if ( \is_wp_error( $ready ) ) {
			return SendResult::from_wp_error( $ready, $this->get_id() );
		}

		if ( ! $subscription->is_valid() ) {
			// Not retryable and not the service's fault — the row itself is unusable.
			return SendResult::gone(
				'upn_incomplete_subscription',
				\__( 'The subscription record is missing its endpoint or keys.', 'ultimate-push-notifications' ),
				0,
				$this->get_id()
			);
		}

		$body = $this->encrypt_payload( $subscription, $payload );
		if ( \is_wp_error( $body ) ) {
			return SendResult::from_wp_error( $body, $this->get_id() );
		}

		$authorization = Vapid::get_authorization_header( $subscription->endpoint );
		if ( \is_wp_error( $authorization ) ) {
			return SendResult::from_wp_error( $authorization, $this->get_id() );
		}

		$headers = array(
			'Authorization'    => $authorization,
			'Content-Type'     => 'application/octet-stream',
			'Content-Encoding' => 'aes128gcm',
			'TTL'              => (string) $this->resolve_ttl( $options ),
			'Urgency'          => $this->resolve_urgency( $options ),
		);

		/*
		 * Topic collapses undelivered messages: a second notification with the
		 * same topic replaces the first rather than stacking. Useful for status
		 * that supersedes itself, such as an order moving through its states.
		 */
		if ( ! empty( $options['topic'] ) ) {
			$headers['Topic'] = \substr( \preg_replace( '/[^A-Za-z0-9_-]/', '', $options['topic'] ), 0, 32 );
		}

		$response = \wp_remote_post(
			$subscription->endpoint,
			array(
				'method'      => 'POST',
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $body,
				'cookies'     => array(),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return SendResult::from_wp_error( $response, $this->get_id() );
		}

		return $this->interpret_response( $response );
	}

	/**
	 * Encrypt the payload for this subscription.
	 *
	 * @param Subscription $subscription
	 * @param array        $payload
	 * @return string|\WP_Error
	 */
	private function encrypt_payload( Subscription $subscription, array $payload ) {

		$json = \wp_json_encode( $payload );
		if ( false === $json ) {
			return new \WP_Error(
				'upn_payload_encode_failed',
				\__( 'The notification payload could not be encoded as JSON.', 'ultimate-push-notifications' )
			);
		}

		$p256dh = Ec::b64_decode( $subscription->p256dh );
		$auth   = Ec::b64_decode( $subscription->auth );

		if ( false === $p256dh || false === $auth ) {
			return new \WP_Error(
				'upn_bad_subscription_key',
				\__( 'The stored subscription keys are not valid base64url.', 'ultimate-push-notifications' )
			);
		}

		return Encryption::encrypt( $json, $p256dh, $auth );
	}

	/**
	 * Turn an HTTP response into a SendResult.
	 *
	 * The status codes here are the whole reason this class exists: 404 and 410
	 * mean prune the row, 429 and 5xx mean try again later, and 401/403 mean the
	 * site's VAPID setup is wrong and every other send will fail the same way.
	 *
	 * @param array $response
	 * @return SendResult
	 */
	private function interpret_response( $response ) {

		$status = (int) \wp_remote_retrieve_response_code( $response );

		// 201 is the specified success code; 200 and 202 are seen in the wild.
		if ( $status >= 200 && $status < 300 ) {
			return SendResult::success( $status, $this->get_id() );
		}

		switch ( $status ) {

			case 404:
			case 410:
				return SendResult::gone(
					'upn_subscription_expired',
					\__( 'The push service reports this subscription no longer exists.', 'ultimate-push-notifications' ),
					$status,
					$this->get_id()
				);

			case 401:
			case 403:
				return SendResult::failure(
					'upn_vapid_rejected',
					\__( 'The push service rejected this site\'s VAPID credentials. Check the key pair and the contact subject.', 'ultimate-push-notifications' ),
					$status,
					$this->get_id()
				);

			case 413:
				return SendResult::failure(
					'upn_payload_too_large',
					\__( 'The push service rejected the notification as too large.', 'ultimate-push-notifications' ),
					$status,
					$this->get_id()
				);

			case 429:
				return SendResult::retry(
					'upn_rate_limited',
					\__( 'The push service is rate limiting this site.', 'ultimate-push-notifications' ),
					$status,
					$this->retry_after_from( $response ),
					$this->get_id()
				);
		}

		if ( $status >= 500 ) {
			return SendResult::retry(
				'upn_push_service_error',
				\__( 'The push service returned a server error.', 'ultimate-push-notifications' ),
				$status,
				$this->retry_after_from( $response ),
				$this->get_id()
			);
		}

		$message = \trim( (string) \wp_remote_retrieve_body( $response ) );

		return SendResult::failure(
			'upn_push_rejected',
			$message
				? \sprintf(
					/* translators: 1: HTTP status, 2: response body */
					\__( 'The push service returned %1$d: %2$s', 'ultimate-push-notifications' ),
					$status,
					\wp_strip_all_tags( \substr( $message, 0, 200 ) )
				)
				: \sprintf(
					/* translators: %d: HTTP status */
					\__( 'The push service returned an unexpected status (%d).', 'ultimate-push-notifications' ),
					$status
				),
			$status,
			$this->get_id()
		);
	}

	/**
	 * Read Retry-After, which may be either a delay in seconds or an HTTP date.
	 *
	 * @param array $response
	 * @return int|null Seconds to wait.
	 */
	private function retry_after_from( $response ) {
		$value = \wp_remote_retrieve_header( $response, 'retry-after' );

		if ( '' === $value || null === $value ) {
			return null;
		}

		if ( \is_numeric( $value ) ) {
			return \max( 0, (int) $value );
		}

		$timestamp = \strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		return \max( 0, $timestamp - \time() );
	}

	/**
	 * Resolve the TTL header value.
	 *
	 * @param array $options
	 * @return int
	 */
	private function resolve_ttl( array $options ) {
		$ttl = isset( $options['ttl'] ) ? (int) $options['ttl'] : self::DEFAULT_TTL;

		/**
		 * Filter how long a push service should hold an undelivered message.
		 *
		 * @param int   $ttl     Seconds.
		 * @param array $options Send options.
		 */
		$ttl = (int) \apply_filters( 'upn_push_ttl', $ttl, $options );

		return \max( 0, $ttl );
	}

	/**
	 * Resolve the Urgency header value.
	 *
	 * @param array $options
	 * @return string
	 */
	private function resolve_urgency( array $options ) {
		$allowed = array( 'very-low', 'low', 'normal', 'high' );
		$urgency = isset( $options['urgency'] ) ? (string) $options['urgency'] : 'normal';

		return \in_array( $urgency, $allowed, true ) ? $urgency : 'normal';
	}

}
