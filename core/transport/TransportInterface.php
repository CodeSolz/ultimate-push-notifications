<?php namespace UltimatePushNotifications\transport;

/**
 * A way of getting a notification to a device.
 *
 * The plugin ships two: native Web Push (the default) and legacy FCM (kept so
 * existing installs keep working through the migration). Everything above this
 * interface — the queue, the event triggers, the delivery log — deals only in
 * Subscription and SendResult and never learns which one it is talking to.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

interface TransportInterface {

	/**
	 * Stable identifier, matching Subscription::TRANSPORT_*.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable name for admin screens.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Is this transport ready to send?
	 *
	 * Returns a WP_Error rather than false so the health check can say what is
	 * missing instead of just that something is.
	 *
	 * @return true|\WP_Error
	 */
	public function is_configured();

	/**
	 * Can this transport deliver to this subscription?
	 *
	 * @param Subscription $subscription
	 * @return bool
	 */
	public function supports( Subscription $subscription );

	/**
	 * Deliver one notification.
	 *
	 * Implementations must never throw and must never return false: every
	 * outcome is a SendResult, so the caller always has a reason it can log.
	 *
	 * @param Subscription $subscription
	 * @param array        $payload Notification fields (title, body, icon, image, click_action…).
	 * @param array        $options Transport hints: ttl, urgency, topic.
	 * @return SendResult
	 */
	public function send( Subscription $subscription, array $payload, array $options = array() );

}
