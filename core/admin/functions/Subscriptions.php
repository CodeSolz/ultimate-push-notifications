<?php namespace UltimatePushNotifications\admin\functions;

/**
 * Browser-facing subscription endpoints.
 *
 * The service-worker client calls these after PushManager.subscribe() and on
 * unsubscribe. Ownership is always the authenticated user — never a value the
 * client supplies — and every response is JSON the client can act on.
 *
 * @package Functions
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\Vapid;

class Subscriptions {

	/**
	 * Save a Web Push subscription for the current user.
	 *
	 * Expects the JSON shape PushSubscription.toJSON() produces:
	 *   endpoint, keys.p256dh, keys.auth
	 * either as a nested array or flattened as endpoint / p256dh / auth.
	 *
	 * @param array $user_input
	 * @return void
	 */
	public function save( $user_input ) {

		$user_id = \get_current_user_id();

		/**
		 * Filter whether visitors who are not logged in may subscribe.
		 *
		 * On by default — a subscriber list is the point of a push plugin. A
		 * site that only wants staff/member alerts can turn it off.
		 *
		 * @param bool $allowed
		 */
		if ( empty( $user_id ) && ! \apply_filters( 'upn_allow_anonymous_subscriptions', true ) ) {
			return $this->respond( false, __( 'Error!', 'ultimate-push-notifications' ), __( 'You need to be logged in to register for notifications.', 'ultimate-push-notifications' ) );
		}

		if ( ! Vapid::has_keys() ) {
			return $this->respond( false, __( 'Not configured', 'ultimate-push-notifications' ), __( 'This site has not generated its push keys yet.', 'ultimate-push-notifications' ) );
		}

		$endpoint = isset( $user_input['endpoint'] ) ? \esc_url_raw( \wp_unslash( $user_input['endpoint'] ) ) : '';

		if ( isset( $user_input['keys'] ) && \is_array( $user_input['keys'] ) ) {
			$p256dh = isset( $user_input['keys']['p256dh'] ) ? $user_input['keys']['p256dh'] : '';
			$auth   = isset( $user_input['keys']['auth'] ) ? $user_input['keys']['auth'] : '';
		} else {
			$p256dh = isset( $user_input['p256dh'] ) ? $user_input['p256dh'] : '';
			$auth   = isset( $user_input['auth'] ) ? $user_input['auth'] : '';
		}

		$p256dh = \preg_replace( '/[^A-Za-z0-9_\-=]/', '', (string) $p256dh );
		$auth   = \preg_replace( '/[^A-Za-z0-9_\-=]/', '', (string) $auth );

		$meta = array(
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'locale'     => isset( $user_input['locale'] ) ? \sanitize_text_field( $user_input['locale'] ) : '',
			'timezone'   => isset( $user_input['timezone'] ) ? \sanitize_text_field( $user_input['timezone'] ) : '',
			'source'     => isset( $user_input['source'] ) ? \esc_url_raw( \wp_unslash( $user_input['source'] ) ) : '',
		);

		// Only record a source on this site: a spoofed off-site URL is not consent evidence.
		if ( '' !== $meta['source'] && 0 !== \strpos( $meta['source'], \home_url() ) ) {
			$meta['source'] = '';
		}

		$saved = SubscriptionStore::save_webpush( (int) $user_id, $endpoint, $p256dh, $auth, $meta );

		if ( \is_wp_error( $saved ) ) {
			return $this->respond( false, __( 'Error!', 'ultimate-push-notifications' ), $saved->get_error_message() );
		}

		return $this->respond(
			true,
			__( 'Success!', 'ultimate-push-notifications' ),
			__( 'This device is now registered for notifications.', 'ultimate-push-notifications' ),
			array( 'subscription_id' => (int) $saved )
		);
	}

	/**
	 * Remove a Web Push subscription.
	 *
	 * Keyed on the endpoint the browser is unsubscribing, and scoped to rows
	 * the current user owns so one member cannot silence another.
	 *
	 * @param array $user_input
	 * @return void
	 */
	public function remove( $user_input ) {

		$user_id      = \get_current_user_id();
		$endpoint     = isset( $user_input['endpoint'] ) ? \esc_url_raw( \wp_unslash( $user_input['endpoint'] ) ) : '';
		$subscription = SubscriptionStore::find_by_endpoint( $endpoint );

		if ( ! $subscription ) {
			// Nothing to do is a success from the browser's point of view.
			return $this->respond( true, __( 'Done', 'ultimate-push-notifications' ), __( 'This device was not registered.', 'ultimate-push-notifications' ) );
		}

		/*
		 * Knowing an endpoint means holding the browser, so an unsubscribe
		 * request is legitimate from anyone who can name it — with one limit:
		 * a logged-out caller may only remove rows that were registered
		 * logged-out. A row owned by an account is removed by that account, or
		 * by an administrator.
		 */
		$owner    = (int) $subscription->user_id;
		$is_owner = ( $owner > 0 && $owner === $user_id ) || ( 0 === $owner && 0 === $user_id );

		if ( ! $is_owner && ! \current_user_can( 'manage_options' ) ) {
			return $this->respond( false, __( 'Access Denied', 'ultimate-push-notifications' ), __( 'That device belongs to another account.', 'ultimate-push-notifications' ) );
		}

		SubscriptionStore::delete( $subscription->id );

		return $this->respond( true, __( 'Done', 'ultimate-push-notifications' ), __( 'This device will no longer receive notifications.', 'ultimate-push-notifications' ) );
	}

	/**
	 * Emit the JSON envelope the front-end expects and stop.
	 *
	 * @param bool   $status
	 * @param string $title
	 * @param string $text
	 * @param array  $extra
	 * @return void
	 */
	private function respond( $status, $title, $text, array $extra = array() ) {
		\wp_send_json(
			\array_merge(
				array(
					'status' => (bool) $status,
					'title'  => $title,
					'text'   => $text,
				),
				$extra
			)
		);
	}

}
