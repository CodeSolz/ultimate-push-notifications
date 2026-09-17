<?php namespace UltimatePushNotifications\transport;

/**
 * Chooses the transport for a subscription.
 *
 * Routing is driven by the subscription itself, not by a site-wide setting, so
 * a site part-way through migrating from FCM to Web Push keeps delivering to
 * both kinds of device without anyone having to flip a switch at the right
 * moment.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class TransportFactory {

	/**
	 * Instantiated transports, keyed by id.
	 *
	 * @var TransportInterface[]
	 */
	private static $transports = null;

	/**
	 * All available transports.
	 *
	 * @return TransportInterface[]
	 */
	public static function all() {
		if ( null !== self::$transports ) {
			return self::$transports;
		}

		$transports = array(
			Subscription::TRANSPORT_WEBPUSH    => new WebPushTransport(),
			Subscription::TRANSPORT_FCM_LEGACY => new FcmLegacyTransport(),
		);

		/**
		 * Filter the registered transports.
		 *
		 * The extension point a Pro addon uses to add a delivery channel — an
		 * email or Slack fallback, say — without touching the send pipeline.
		 * Entries must implement TransportInterface; anything else is dropped.
		 *
		 * @param TransportInterface[] $transports Keyed by transport id.
		 */
		$transports = (array) \apply_filters( 'upn_transports', $transports );

		self::$transports = array();
		foreach ( $transports as $id => $transport ) {
			if ( $transport instanceof TransportInterface ) {
				self::$transports[ $id ] = $transport;
			}
		}

		return self::$transports;
	}

	/**
	 * Get one transport by id.
	 *
	 * @param string $id
	 * @return TransportInterface|null
	 */
	public static function get( $id ) {
		$transports = self::all();
		return isset( $transports[ $id ] ) ? $transports[ $id ] : null;
	}

	/**
	 * The transport that can deliver to this subscription.
	 *
	 * @param Subscription $subscription
	 * @return TransportInterface|null
	 */
	public static function for_subscription( Subscription $subscription ) {
		foreach ( self::all() as $transport ) {
			if ( $transport->supports( $subscription ) ) {
				return $transport;
			}
		}

		return null;
	}

	/**
	 * The transport a new device should register with.
	 *
	 * Web Push unless it cannot run here, in which case a site with a legacy
	 * Firebase setup still has something that will at least attempt to send.
	 *
	 * @return TransportInterface
	 */
	public static function preferred() {
		$webpush = self::get( Subscription::TRANSPORT_WEBPUSH );

		if ( $webpush && true === $webpush->is_configured() ) {
			return $webpush;
		}

		$fcm = self::get( Subscription::TRANSPORT_FCM_LEGACY );
		if ( $fcm && true === $fcm->is_configured() ) {
			return $fcm;
		}

		// Nothing is fully configured. Return Web Push so the setup guidance the
		// admin sees is for the transport we actually want them on.
		return $webpush ? $webpush : new WebPushTransport();
	}

	/**
	 * Deliver one notification, routing automatically.
	 *
	 * @param Subscription $subscription
	 * @param array        $payload
	 * @param array        $options
	 * @return SendResult
	 */
	public static function send( Subscription $subscription, array $payload, array $options = array() ) {

		$transport = self::for_subscription( $subscription );

		if ( ! $transport ) {
			return SendResult::failure(
				'upn_no_transport',
				\sprintf(
					/* translators: %s: transport id */
					\__( 'No transport is registered that can deliver to a "%s" subscription.', 'ultimate-push-notifications' ),
					$subscription->transport
				)
			);
		}

		return $transport->send( $subscription, $payload, $options );
	}

	/**
	 * Reset the cache. Tests and transport-changing settings saves use this.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$transports = null;
	}

}
