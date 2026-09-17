<?php namespace UltimatePushNotifications\actions;

/**
 * Personal-data export and erasure.
 *
 * Hooks into Tools → Export/Erase Personal Data. A push subscription is
 * personal data: it identifies a specific browser on a specific device and,
 * for a logged-in user, ties it to their account. Endpoints and keys are also
 * send credentials, so the export reports that a subscription exists and its
 * metadata, but never the endpoint or keys themselves.
 *
 * Anonymous rows have no identity to look up by email, so they are outside
 * these tools; a visitor removes theirs by unsubscribing in the browser.
 *
 * @package Action
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\automation\Preferences;
use UltimatePushNotifications\automation\Rules;

class Upn_Privacy {

	function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'deleted_user', array( $this, 'on_user_deleted' ) );
	}

	/**
	 * @param array $exporters
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['ultimate-push-notifications'] = array(
			'exporter_friendly_name' => __( 'Push notification subscriptions', 'ultimate-push-notifications' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array $erasers
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['ultimate-push-notifications'] = array(
			'eraser_friendly_name' => __( 'Push notification subscriptions', 'ultimate-push-notifications' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export the subscriptions belonging to the account with this email.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		$data = array();

		if ( $user ) {
			foreach ( SubscriptionStore::for_user( $user->ID ) as $subscription ) {
				$row = self::row( $subscription->id );
				if ( ! $row ) {
					continue;
				}

				$data[] = array(
					'group_id'    => 'upn_subscriptions',
					'group_label' => __( 'Push notification subscriptions', 'ultimate-push-notifications' ),
					'item_id'     => 'upn-subscription-' . $subscription->id,
					'data'        => array(
						array( 'name' => __( 'Device', 'ultimate-push-notifications' ), 'value' => trim( $row->browser . ' / ' . $row->os . ' / ' . $row->device, ' /' ) ),
						array( 'name' => __( 'Push service', 'ultimate-push-notifications' ), 'value' => $subscription->service_host() ),
						array( 'name' => __( 'Language', 'ultimate-push-notifications' ), 'value' => (string) $row->locale ),
						array( 'name' => __( 'Time zone', 'ultimate-push-notifications' ), 'value' => (string) $row->timezone ),
						array( 'name' => __( 'Subscribed on', 'ultimate-push-notifications' ), 'value' => (string) $row->consent_on ),
						array( 'name' => __( 'Subscribed from', 'ultimate-push-notifications' ), 'value' => (string) $row->consent_source ),
						array( 'name' => __( 'Last seen', 'ultimate-push-notifications' ), 'value' => (string) $row->last_seen_on ),
						array( 'name' => __( 'Notifications delivered', 'ultimate-push-notifications' ), 'value' => (int) $row->total_sent_success_notifications ),
					),
				);
			}

			$muted = array();
			foreach ( Rules::all() as $id => $rule ) {
				if ( Preferences::is_muted( $user->ID, $id ) ) {
					$muted[] = $rule['name'];
				}
			}
			if ( $muted ) {
				$data[] = array(
					'group_id'    => 'upn_preferences',
					'group_label' => __( 'Push notification preferences', 'ultimate-push-notifications' ),
					'item_id'     => 'upn-preferences',
					'data'        => array(
						array( 'name' => __( 'Turned off', 'ultimate-push-notifications' ), 'value' => implode( ', ', $muted ) ),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase the subscriptions belonging to the account with this email.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		$user    = get_user_by( 'email', $email );
		$removed = 0;

		if ( $user ) {
			foreach ( SubscriptionStore::for_user( $user->ID ) as $subscription ) {
				if ( SubscriptionStore::delete( $subscription->id ) ) {
					$removed++;
				}
			}
			Preferences::forget( $user->ID );
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => $removed
				? array( sprintf( /* translators: %d: count */ _n( '%d push subscription removed.', '%d push subscriptions removed.', $removed, 'ultimate-push-notifications' ), $removed ) )
				: array(),
			'done'           => true,
		);
	}

	/**
	 * A deleted account takes its devices with it.
	 *
	 * @param int $user_id
	 * @return void
	 */
	public function on_user_deleted( $user_id ) {
		foreach ( SubscriptionStore::for_user( (int) $user_id ) as $subscription ) {
			SubscriptionStore::delete( $subscription->id );
		}
	}

	/**
	 * Raw row for the metadata columns Subscription does not carry.
	 *
	 * @param int $id
	 * @return object|null
	 */
	private static function row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . SubscriptionStore::table() . '` WHERE id = %d', (int) $id ) );
	}

}
