<?php namespace UltimatePushNotifications\admin\functions;

/**
 * AJAX handlers behind the Compose screen.
 *
 * @package Functions
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\messaging\Composer;
use UltimatePushNotifications\messaging\MergeTags;
use UltimatePushNotifications\transport\SubscriptionStore;

class Compose {

	/**
	 * Capability required to send broadcasts.
	 *
	 * Filterable so an agency can grant it to a marketing role without
	 * handing over manage_options.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability needed to compose and send notifications.
		 *
		 * @param string $cap
		 */
		return (string) \apply_filters( 'upn_compose_capability', 'manage_options' );
	}

	/**
	 * Live recipient count for the audience the form describes.
	 *
	 * @param array $user_input
	 * @return void
	 */
	public function count( $user_input ) {
		if ( ! \current_user_can( self::capability() ) ) {
			return $this->respond( false, __( 'Access Denied', 'ultimate-push-notifications' ), '' );
		}

		$audience = Composer::sanitize_audience( isset( $user_input['audience'] ) && \is_array( $user_input['audience'] ) ? $user_input['audience'] : array() );

		return $this->respond( true, '', '', array( 'count' => Composer::count( $audience ) ) );
	}

	/**
	 * Send the broadcast.
	 *
	 * @param array $user_input
	 * @return void
	 */
	public function send( $user_input ) {
		if ( ! \current_user_can( self::capability() ) ) {
			return $this->respond( false, __( 'Access Denied', 'ultimate-push-notifications' ), __( 'You do not have permission to send notifications.', 'ultimate-push-notifications' ) );
		}

		$fields = Composer::sanitize( isset( $user_input['fields'] ) && \is_array( $user_input['fields'] ) ? \wp_unslash( $user_input['fields'] ) : array() );
		if ( \is_wp_error( $fields ) ) {
			return $this->respond( false, __( 'Check the form', 'ultimate-push-notifications' ), $fields->get_error_message() );
		}

		$audience = Composer::sanitize_audience( isset( $user_input['audience'] ) && \is_array( $user_input['audience'] ) ? $user_input['audience'] : array() );

		$result = Composer::send( $fields, $audience, array( 'user' => \wp_get_current_user() ) );

		if ( \is_wp_error( $result ) ) {
			return $this->respond( false, __( 'Not sent', 'ultimate-push-notifications' ), $result->get_error_message() );
		}

		$text = $result['queued']
			? \sprintf(
				/* translators: %d: count */
				\_n( 'Queued for %d device. Delivery runs in the background — see the Health page for progress.', 'Queued for %d devices. Delivery runs in the background — see the Health page for progress.', $result['recipients'], 'ultimate-push-notifications' ),
				$result['recipients']
			)
			: \sprintf(
				/* translators: %d: count */
				\_n( 'Sent to %d device.', 'Sent to %d devices.', $result['recipients'], 'ultimate-push-notifications' ),
				$result['recipients']
			);

		return $this->respond( true, __( 'Sent', 'ultimate-push-notifications' ), $text, array( 'log_id' => $result['log_id'], 'recipients' => $result['recipients'] ) );
	}

	/**
	 * Send the composed notification to the current user's own devices only.
	 *
	 * The "see it on my phone before I send it to everyone" step.
	 *
	 * @param array $user_input
	 * @return void
	 */
	public function preview( $user_input ) {
		if ( ! \current_user_can( self::capability() ) ) {
			return $this->respond( false, __( 'Access Denied', 'ultimate-push-notifications' ), '' );
		}

		$fields = Composer::sanitize( isset( $user_input['fields'] ) && \is_array( $user_input['fields'] ) ? \wp_unslash( $user_input['fields'] ) : array() );
		if ( \is_wp_error( $fields ) ) {
			return $this->respond( false, __( 'Check the form', 'ultimate-push-notifications' ), $fields->get_error_message() );
		}

		$me = \get_current_user_id();
		if ( ! SubscriptionStore::for_user( $me ) ) {
			return $this->respond( false, __( 'No device', 'ultimate-push-notifications' ), __( 'Register this browser on “Register My Device” first, then preview.', 'ultimate-push-notifications' ) );
		}

		$result = Composer::send(
			$fields,
			array( 'user_ids' => array( $me ), 'transport' => 'webpush' ),
			array( 'user' => \wp_get_current_user() ),
			'preview'
		);

		if ( \is_wp_error( $result ) ) {
			return $this->respond( false, __( 'Not sent', 'ultimate-push-notifications' ), $result->get_error_message() );
		}

		return $this->respond( true, __( 'Preview sent', 'ultimate-push-notifications' ), __( 'Check your device.', 'ultimate-push-notifications' ) );
	}

	/**
	 * The merge tag reference, for the screen.
	 *
	 * @return array
	 */
	public static function tag_reference() {
		return MergeTags::grouped();
	}

	private function respond( $status, $title, $text, array $extra = array() ) {
		\wp_send_json( \array_merge( array( 'status' => (bool) $status, 'title' => $title, 'text' => $text ), $extra ) );
	}

}
