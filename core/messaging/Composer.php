<?php namespace UltimatePushNotifications\messaging;

use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\queue\BroadcastJob;
use UltimatePushNotifications\queue\Runner;
use UltimatePushNotifications\transport\DeliveryLog;
use UltimatePushNotifications\transport\SubscriptionStore;

/**
 * Sends a one-off notification to an audience.
 *
 * This is the "Custom Notification" the support forum asked for and never
 * got. The entry point is deliberately transport- and queue-agnostic: it
 * validates, renders merge tags, opens a log row, and either fans out through
 * the queue or delivers inline when no scheduler exists. Everything a Pro
 * scheduler or automation would do is "call Composer::send() later".
 *
 * @package Messaging
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Composer {

	/** Length limits that fit every platform's notification UI. */
	const MAX_TITLE = 100;
	const MAX_BODY  = 300;

	/** Action buttons: browsers show at most two, with short labels. */
	const MAX_ACTIONS      = 2;
	const MAX_ACTION_TITLE = 24;

	/**
	 * Sanitise and validate composer input.
	 *
	 * @param array $fields title, body, icon, image, click_action, tag, urgency, ttl
	 * @return array|\WP_Error Clean fields.
	 */
	public static function sanitize( array $fields ) {
		$clean = array(
			'title'        => isset( $fields['title'] ) ? \wp_strip_all_tags( \trim( (string) $fields['title'] ) ) : '',
			'body'         => isset( $fields['body'] ) ? \wp_strip_all_tags( \trim( (string) $fields['body'] ) ) : '',
			'icon'         => isset( $fields['icon'] ) ? \esc_url_raw( \trim( (string) $fields['icon'] ) ) : '',
			'image'        => isset( $fields['image'] ) ? \esc_url_raw( \trim( (string) $fields['image'] ) ) : '',
			'click_action' => isset( $fields['click_action'] ) ? \esc_url_raw( \trim( (string) $fields['click_action'] ) ) : '',
			'tag'          => isset( $fields['tag'] ) ? \sanitize_key( $fields['tag'] ) : '',
			'urgency'      => isset( $fields['urgency'] ) && \in_array( $fields['urgency'], array( 'very-low', 'low', 'normal', 'high' ), true ) ? $fields['urgency'] : 'normal',
			'ttl'          => isset( $fields['ttl'] ) ? \max( 0, \min( 2419200, (int) $fields['ttl'] ) ) : 86400,
			'actions'      => self::sanitize_actions( isset( $fields['actions'] ) ? $fields['actions'] : array() ),
		);

		/**
		 * Anything else a send carries for an extension (a send-time choice, a
		 * flag). Sanitisers on this filter own their keys; the bucket travels
		 * with the fields into the send options and is never part of the
		 * payload a device receives.
		 *
		 * @param array $extra
		 * @param array $fields Raw input.
		 */
		$extra          = \apply_filters( 'upn_sanitize_fields_extra', array(), $fields );
		$clean['extra'] = \is_array( $extra ) ? $extra : array();

		if ( '' === $clean['title'] ) {
			return new \WP_Error( 'upn_compose_no_title', \__( 'A title is required.', 'ultimate-push-notifications' ) );
		}

		if ( \mb_strlen( $clean['title'] ) > self::MAX_TITLE ) {
			return new \WP_Error( 'upn_compose_title_long', \sprintf( \__( 'The title must be %d characters or fewer.', 'ultimate-push-notifications' ), self::MAX_TITLE ) );
		}

		if ( \mb_strlen( $clean['body'] ) > self::MAX_BODY ) {
			return new \WP_Error( 'upn_compose_body_long', \sprintf( \__( 'The message must be %d characters or fewer.', 'ultimate-push-notifications' ), self::MAX_BODY ) );
		}

		foreach ( array( 'icon', 'image', 'click_action' ) as $url_field ) {
			if ( '' !== $clean[ $url_field ] && 0 !== \strpos( $clean[ $url_field ], 'https://' ) && 0 !== \strpos( $clean[ $url_field ], 'http://' ) ) {
				return new \WP_Error( 'upn_compose_bad_url', \sprintf( \__( 'The %s must be a full URL.', 'ultimate-push-notifications' ), $url_field ) );
			}
		}

		return $clean;
	}

	/**
	 * Up to two action buttons: a short title and a full URL each. Browsers
	 * show at most two; a button without both parts is dropped.
	 *
	 * @param mixed $input
	 * @return array<int,array{title:string,url:string}>
	 */
	public static function sanitize_actions( $input ) {
		$out = array();
		foreach ( \is_array( $input ) ? $input : array() as $a ) {
			if ( ! \is_array( $a ) ) {
				continue;
			}
			$title = isset( $a['title'] ) ? \mb_substr( \wp_strip_all_tags( \trim( (string) $a['title'] ) ), 0, self::MAX_ACTION_TITLE ) : '';
			$url   = isset( $a['url'] ) ? \esc_url_raw( \trim( (string) $a['url'] ) ) : '';
			if ( '' === $title || ! \preg_match( '#^https?://#i', $url ) ) {
				continue;
			}
			$out[] = array( 'title' => $title, 'url' => $url );
			if ( \count( $out ) >= self::MAX_ACTIONS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Sanitise an audience description from a form.
	 *
	 * @param array $input who, roles[], browser, os, device, locale, seen_within_days
	 * @return array
	 */
	public static function sanitize_audience( array $input ) {
		$audience = array();

		$who = isset( $input['who'] ) ? \sanitize_key( $input['who'] ) : 'any';
		$audience['who'] = \in_array( $who, array( 'any', 'anonymous', 'logged_in', 'roles' ), true ) ? $who : 'any';

		if ( 'roles' === $audience['who'] ) {
			$roles = isset( $input['roles'] ) ? \array_map( 'sanitize_key', (array) $input['roles'] ) : array();
			$valid = \array_keys( \wp_roles()->roles );
			$audience['roles'] = \array_values( \array_intersect( $roles, $valid ) );
			if ( ! $audience['roles'] ) {
				// "roles" with none chosen means nobody, and the count will say so.
				$audience['roles'] = array( '__none__' );
			}
		}

		foreach ( array( 'browser', 'os', 'device' ) as $k ) {
			if ( ! empty( $input[ $k ] ) ) {
				$audience[ $k ] = \sanitize_key( $input[ $k ] );
			}
		}

		if ( ! empty( $input['locale'] ) ) {
			$audience['locale'] = \preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input['locale'] );
		}

		if ( ! empty( $input['seen_within_days'] ) ) {
			$audience['seen_within_days'] = \max( 1, \min( 365, (int) $input['seen_within_days'] ) );
		}

		// Only sendable transports; a legacy FCM row cannot receive anything.
		$audience['transport'] = 'webpush';

		/**
		 * Extend a sanitised audience from the raw form input — e.g. carry a
		 * segment id that upn_audience_where then resolves.
		 *
		 * @param array $audience Sanitised.
		 * @param array $input    Raw.
		 */
		return (array) \apply_filters( 'upn_sanitize_audience', $audience, $input );
	}

	/**
	 * How many devices would receive this.
	 *
	 * @param array $audience Sanitised.
	 * @return int
	 */
	public static function count( array $audience ) {
		return SubscriptionStore::count_audience( $audience );
	}

	/**
	 * Send.
	 *
	 * @param array $fields   Sanitised fields.
	 * @param array $audience Sanitised audience.
	 * @param array $context  Merge-tag context (post, user, extra…).
	 * @param string $type    Log type. Default "broadcast".
	 * @return array|\WP_Error array{log_id:int, recipients:int, queued:bool}
	 */
	public static function send( array $fields, array $audience, array $context = array(), $type = 'broadcast' ) {

		$recipients = self::count( $audience );

		if ( 0 === $recipients ) {
			return new \WP_Error( 'upn_compose_no_recipients', \__( 'No registered devices match this audience.', 'ultimate-push-notifications' ) );
		}

		$payload = SendNotifications::build_payload(
			array(
				'title'        => MergeTags::render( $fields['title'], $context ),
				'body'         => MergeTags::render( $fields['body'], $context ),
				'icon'         => $fields['icon'],
				'image'        => $fields['image'],
				'click_action' => '' !== $fields['click_action'] ? MergeTags::render( $fields['click_action'], $context ) : '',
				'tag'          => $fields['tag'],
				'actions'      => isset( $fields['actions'] ) ? $fields['actions'] : array(),
			)
		);

		$options = array(
			'type'    => (string) $type,
			'urgency' => $fields['urgency'],
			'ttl'     => $fields['ttl'],
		);
		if ( '' !== $fields['tag'] ) {
			$options['topic'] = $fields['tag'];
		}
		if ( ! empty( $fields['extra'] ) && \is_array( $fields['extra'] ) ) {
			$options['extra'] = $fields['extra'];
		}

		/**
		 * Filter a broadcast just before it is sent or queued.
		 *
		 * Return a WP_Error to abort.
		 *
		 * @param array $payload
		 * @param array $audience
		 * @param array $options
		 */
		$payload = \apply_filters( 'upn_before_broadcast', $payload, $audience, $options );
		if ( \is_wp_error( $payload ) ) {
			return $payload;
		}

		$log_id = DeliveryLog::open( $type, $payload, $recipients );

		if ( Runner::enabled() ) {
			BroadcastJob::enqueue( (int) $log_id, $audience, $payload, $options );
			Runner::schedule( 0 );

			return array( 'log_id' => (int) $log_id, 'recipients' => $recipients, 'queued' => true );
		}

		// No scheduler: deliver now, page by page, rolling results into the log.
		$options['log_id'] = (int) $log_id;
		$offset = 0;
		do {
			$page = SubscriptionStore::query_audience( $audience, 200, $offset );
			foreach ( $page as $subscription ) {
				if ( '' !== SendNotifications::gate( $subscription, $payload, $options )['skip'] ) {
					DeliveryLog::apply_skip( (int) $log_id );
					continue;
				}
				$result = SendNotifications::deliver( $subscription, $payload, $options );
				DeliveryLog::apply_result( (int) $log_id, $result );
			}
			$offset += 200;
		} while ( 200 === \count( $page ) );

		return array( 'log_id' => (int) $log_id, 'recipients' => $recipients, 'queued' => false );
	}

}
