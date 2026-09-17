<?php namespace UltimatePushNotifications\transport;

/**
 * Persistence for subscriptions.
 *
 * The only class that writes to upn_user_devices. Every row is addressed by its
 * primary key — never by a LIKE on a token prefix, which is how the previous
 * implementation could delete a different user's device when two Firebase
 * instance-id prefixes collided.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class SubscriptionStore {

	/**
	 * Fully-prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'upn_user_devices';
	}

	/* ---------------------------------------------------------------------
	 * Reads
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch one subscription by id.
	 *
	 * @param int $id
	 * @return Subscription|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE id = %d', (int) $id )
		);

		return $row ? Subscription::from_row( $row ) : null;
	}

	/**
	 * Fetch the subscription registered against a Web Push endpoint.
	 *
	 * Endpoints are unique per browser installation, so this is the natural
	 * key for re-subscription and for reconciling a device across logins.
	 *
	 * @param string $endpoint
	 * @return Subscription|null
	 */
	public static function find_by_endpoint( $endpoint ) {
		global $wpdb;

		if ( '' === $endpoint ) {
			return null;
		}

		// The endpoint column is indexed on its first 191 characters; matching on
		// the prefix first lets MySQL use it, and the full compare settles ties.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . self::table() . '` WHERE endpoint LIKE %s AND endpoint = %s LIMIT 1',
				$wpdb->esc_like( \substr( $endpoint, 0, 191 ) ) . '%',
				$endpoint
			)
		);

		return $row ? Subscription::from_row( $row ) : null;
	}

	/**
	 * Fetch the subscription holding a legacy FCM token.
	 *
	 * @param string $token
	 * @return Subscription|null
	 */
	public static function find_by_token( $token ) {
		global $wpdb;

		if ( '' === $token ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . self::table() . '` WHERE token LIKE %s AND token = %s LIMIT 1',
				$wpdb->esc_like( \substr( $token, 0, 64 ) ) . '%',
				$token
			)
		);

		return $row ? Subscription::from_row( $row ) : null;
	}

	/**
	 * Every subscription belonging to a user.
	 *
	 * @param int $user_id
	 * @return Subscription[]
	 */
	public static function for_user( $user_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . self::table() . '` WHERE user_id = %d ORDER BY id ASC',
				(int) $user_id
			)
		);

		return self::hydrate( $rows );
	}

	/**
	 * Build the WHERE clause for an audience.
	 *
	 * The shape every consumer of "who should get this" uses — the composer,
	 * the subscriber list, the automations. Everything is bound through
	 * prepare(); the role filter resolves to user ids first because roles live
	 * in usermeta and joining on it from here would be slower than two queries.
	 *
	 * @param array $audience {
	 *     @type string       $who       'any' | 'anonymous' | 'logged_in'. Default 'any'.
	 *     @type string       $transport Restrict to one transport.
	 *     @type string[]     $roles     WordPress role slugs (implies logged_in).
	 *     @type int[]        $user_ids  Explicit users.
	 *     @type string       $browser   Browser family.
	 *     @type string       $os        OS family.
	 *     @type string       $device    desktop | mobile | tablet.
	 *     @type string       $locale    Prefix match, e.g. "de" matches de-DE.
	 *     @type int          $seen_within_days  Only devices active recently.
	 * }
	 * @return array{sql:string, params:array}
	 */
	public static function audience_where( array $audience ) {
		$clauses = array();
		$params  = array();

		$who = isset( $audience['who'] ) ? (string) $audience['who'] : 'any';

		if ( ! empty( $audience['roles'] ) ) {
			$ids = \get_users(
				array(
					'role__in' => \array_map( 'sanitize_key', (array) $audience['roles'] ),
					'fields'   => 'ID',
					'number'   => -1,
				)
			);
			$ids = \array_map( 'intval', (array) $ids );
			if ( ! $ids ) {
				// A role nobody holds matches nobody — not everybody.
				$clauses[] = '1 = 0';
			} else {
				$clauses[] = 'user_id IN (' . \implode( ',', \array_fill( 0, \count( $ids ), '%d' ) ) . ')';
				$params    = \array_merge( $params, $ids );
			}
		} elseif ( ! empty( $audience['user_ids'] ) ) {
			$ids       = \array_map( 'intval', (array) $audience['user_ids'] );
			$clauses[] = 'user_id IN (' . \implode( ',', \array_fill( 0, \count( $ids ), '%d' ) ) . ')';
			$params    = \array_merge( $params, $ids );
		} elseif ( 'anonymous' === $who ) {
			$clauses[] = '(user_id IS NULL OR user_id = 0)';
		} elseif ( 'logged_in' === $who ) {
			$clauses[] = 'user_id > 0';
		}

		if ( ! empty( $audience['exclude_user_ids'] ) ) {
			$skip = \array_filter( \array_map( 'intval', (array) $audience['exclude_user_ids'] ), function ( $id ) { return $id > 0; } );
			if ( $skip ) {
				// NOT IN is NULL for a NULL user_id, which would silently drop every anonymous row.
				$clauses[] = '(user_id IS NULL OR user_id NOT IN (' . \implode( ',', \array_fill( 0, \count( $skip ), '%d' ) ) . '))';
				$params    = \array_merge( $params, \array_values( $skip ) );
			}
		}

		foreach ( array( 'transport', 'browser', 'os', 'device' ) as $column ) {
			if ( ! empty( $audience[ $column ] ) ) {
				$clauses[] = "{$column} = %s";
				$params[]  = \sanitize_key( $audience[ $column ] );
			}
		}

		if ( ! empty( $audience['locale'] ) ) {
			global $wpdb;
			$clauses[] = 'locale LIKE %s';
			$params[]  = $wpdb->esc_like( \preg_replace( '/[^A-Za-z0-9_\-]/', '', $audience['locale'] ) ) . '%';
		}

		if ( ! empty( $audience['seen_within_days'] ) ) {
			$clauses[] = 'last_seen_on >= %s';
			// Rows are stamped with current_time( 'mysql' ) — site-local — so the bound must be too.
			$params[]  = \gmdate( 'Y-m-d H:i:s', \current_time( 'timestamp' ) - ( (int) $audience['seen_within_days'] * DAY_IN_SECONDS ) );
		}

		/**
		 * Extend the audience with more clauses on the subscriber table.
		 *
		 * Each clause is SQL against this table's columns with %d/%s
		 * placeholders; its params follow in order. This is how segments
		 * narrow an audience without the store knowing what a segment is.
		 *
		 * @param array{0:string[],1:array} $pair     Clauses and params so far.
		 * @param array                     $audience The audience being resolved.
		 */
		$pair = \apply_filters( 'upn_audience_where', array( $clauses, $params ), $audience );
		if ( \is_array( $pair ) && isset( $pair[0], $pair[1] ) && \is_array( $pair[0] ) && \is_array( $pair[1] ) ) {
			list( $clauses, $params ) = $pair;
		}

		return array(
			'sql'    => $clauses ? ' WHERE ' . \implode( ' AND ', $clauses ) : '',
			'params' => $params,
		);
	}

	/**
	 * How many subscriptions an audience contains.
	 *
	 * @param array $audience See audience_where().
	 * @return int
	 */
	public static function count_audience( array $audience ) {
		global $wpdb;

		$where = self::audience_where( $audience );
		$sql   = 'SELECT COUNT(id) FROM `' . self::table() . '`' . $where['sql'];

		return (int) ( $where['params'] ? $wpdb->get_var( $wpdb->prepare( $sql, $where['params'] ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Fetch an audience, a page at a time.
	 *
	 * @param array $audience See audience_where().
	 * @param int   $limit
	 * @param int   $offset
	 * @return Subscription[]
	 */
	public static function query_audience( array $audience, $limit = 500, $offset = 0 ) {
		global $wpdb;

		$where  = self::audience_where( $audience );
		$sql    = 'SELECT * FROM `' . self::table() . '`' . $where['sql'] . ' ORDER BY id ASC LIMIT %d OFFSET %d';
		$params = \array_merge( $where['params'], array( \max( 1, (int) $limit ), \max( 0, (int) $offset ) ) );

		return self::hydrate( $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) );
	}

	/**
	 * Turn a list of rows into Subscriptions, dropping any that cannot be sent to.
	 *
	 * Callers that already hold rows (the preference resolver selects them in
	 * the same query as the user's settings) use this rather than re-querying.
	 *
	 * @param array $rows
	 * @return Subscription[]
	 */
	public static function hydrate( $rows ) {
		$subscriptions = array();

		foreach ( (array) $rows as $row ) {
			$subscription = Subscription::from_row( $row );
			if ( $subscription->is_valid() ) {
				$subscriptions[] = $subscription;
			}
		}

		return $subscriptions;
	}

	/**
	 * Count subscriptions, optionally by transport.
	 *
	 * @param string $transport Empty for all.
	 * @return int
	 */
	public static function count( $transport = '' ) {
		global $wpdb;

		if ( '' === $transport ) {
			return (int) $wpdb->get_var( 'SELECT COUNT(id) FROM `' . self::table() . '`' );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(id) FROM `' . self::table() . '` WHERE transport = %s', $transport )
		);
	}

	/**
	 * Subscriptions registered in the last N days.
	 *
	 * @param int $days
	 * @return int
	 */
	public static function count_since_days( $days ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(id) FROM `' . self::table() . '` WHERE registered_on >= %s',
				\gmdate( 'Y-m-d H:i:s', \time() - \max( 1, (int) $days ) * DAY_IN_SECONDS )
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Writes
	 * ------------------------------------------------------------------ */

	/**
	 * Store or refresh a Web Push subscription.
	 *
	 * Keyed on the endpoint. A browser that re-subscribes (new keys, same
	 * endpoint) updates its row; a browser whose user changed (someone else
	 * logged in) has the row reassigned to the new owner, because a device
	 * belongs to whoever is holding it.
	 *
	 * A user_id of 0 is an anonymous visitor. That is a first-class row, not
	 * an error: it is what a subscriber list is made of.
	 *
	 * @param int          $user_id  0 for anonymous.
	 * @param string       $endpoint Push service URL.
	 * @param string       $p256dh   Base64url, 65 bytes decoded.
	 * @param string       $auth     Base64url, 16 bytes decoded.
	 * @param string|array $meta     A user-agent string (legacy call shape), or an
	 *                               array: user_agent, locale, timezone, source.
	 * @return int|\WP_Error Row id.
	 */
	public static function save_webpush( $user_id, $endpoint, $p256dh, $auth, $meta = '' ) {
		if ( ! \is_array( $meta ) ) {
			$meta = array( 'user_agent' => (string) $meta );
		}
		$meta = \array_merge(
			array( 'user_agent' => '', 'locale' => '', 'timezone' => '', 'source' => '' ),
			$meta
		);
		global $wpdb;

		$endpoint = \trim( (string) $endpoint );

		if ( ! self::is_acceptable_endpoint( $endpoint ) ) {
			return new \WP_Error(
				'upn_bad_endpoint',
				\__( 'The subscription endpoint must be an https:// URL.', 'ultimate-push-notifications' )
			);
		}

		$p256dh_raw = Ec::b64_decode( $p256dh );
		if ( ! Ec::is_valid_point( $p256dh_raw ) ) {
			return new \WP_Error(
				'upn_bad_subscription_key',
				\__( 'The subscription p256dh key is not a valid P-256 point.', 'ultimate-push-notifications' )
			);
		}

		$auth_raw = Ec::b64_decode( $auth );
		if ( ! \is_string( $auth_raw ) || 16 !== \strlen( $auth_raw ) ) {
			return new \WP_Error(
				'upn_bad_auth_secret',
				\__( 'The subscription auth secret must be 16 bytes.', 'ultimate-push-notifications' )
			);
		}

		$now = \current_time( 'mysql' );
		$ua  = UserAgent::parse( $meta['user_agent'] );

		$data = array(
			'user_id'      => (int) $user_id,
			'transport'    => Subscription::TRANSPORT_WEBPUSH,
			'endpoint'     => $endpoint,
			'p256dh'       => Ec::b64_encode( $p256dh_raw ),
			'auth_secret'  => Ec::b64_encode( $auth_raw ),
			'user_agent'   => self::clip( $meta['user_agent'], 255 ),
			'browser'      => $ua['browser'],
			'os'           => $ua['os'],
			'device'       => $ua['device'],
			'locale'       => self::clip( \preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $meta['locale'] ), 20 ),
			'timezone'     => self::clip( \preg_replace( '/[^A-Za-z0-9_\/\-+]/', '', (string) $meta['timezone'] ), 64 ),
			'last_seen_on' => $now,
		);

		$existing = self::find_by_endpoint( $endpoint );

		if ( $existing ) {
			$updated = $wpdb->update( self::table(), $data, array( 'id' => $existing->id ) );

			if ( false === $updated ) {
				return self::db_error();
			}

			return $existing->id;
		}

		/*
		 * Consent is recorded once, at first registration: when and from which
		 * page. A re-subscribe refreshes keys, not consent.
		 */
		$data['registered_on']  = $now;
		$data['consent_on']     = $now;
		$data['consent_source'] = self::clip( \esc_url_raw( (string) $meta['source'] ), 500 );

		if ( false === $wpdb->insert( self::table(), $data ) ) {
			return self::db_error();
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * A new subscription — not a re-subscribe or a key refresh, which
		 * update the existing row above. A welcome sequence starts here.
		 *
		 * @param int $id
		 * @param int $user_id 0 for an anonymous subscriber.
		 */
		\do_action( 'upn_subscription_created', $id, (int) $user_id );

		return $id;
	}

	/**
	 * Store or refresh a legacy FCM registration token.
	 *
	 * Kept for sites still running the Firebase client. Keyed on the full
	 * token, never a prefix.
	 *
	 * @param int    $user_id
	 * @param string $token
	 * @param string $device_id
	 * @return int|\WP_Error Row id.
	 */
	public static function save_fcm_token( $user_id, $token, $device_id = '' ) {
		global $wpdb;

		$token = \trim( (string) $token );

		if ( '' === $token || \strlen( $token ) > 4096 ) {
			return new \WP_Error(
				'upn_bad_token',
				\__( 'No valid device token was provided.', 'ultimate-push-notifications' )
			);
		}

		$now  = \current_time( 'mysql' );
		$data = array(
			'user_id'      => (int) $user_id,
			'transport'    => Subscription::TRANSPORT_FCM_LEGACY,
			'token'        => $token,
			'device_id'    => self::clip( $device_id, 4096 ),
			'last_seen_on' => $now,
		);

		$existing = self::find_by_token( $token );

		if ( $existing ) {
			if ( false === $wpdb->update( self::table(), $data, array( 'id' => $existing->id ) ) ) {
				return self::db_error();
			}
			return $existing->id;
		}

		$data['registered_on'] = $now;

		if ( false === $wpdb->insert( self::table(), $data ) ) {
			return self::db_error();
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete one subscription.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$deleted = $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Record what happened when we tried to send to a subscription.
	 *
	 * Success and failure counters feed the device list and the health check;
	 * last_error is what an administrator sees when they ask why a device is
	 * not receiving anything. A "gone" result deletes the row — the push
	 * service has told us in as many words that it will never deliver again,
	 * and every retry would be a wasted request and a misleading failure count.
	 *
	 * @param Subscription $subscription
	 * @param SendResult   $result
	 * @return void
	 */
	public static function record_result( Subscription $subscription, SendResult $result ) {
		global $wpdb;

		if ( $subscription->id <= 0 ) {
			return;
		}

		if ( $result->subscription_gone ) {
			self::delete( $subscription->id );

			/**
			 * Fires after a subscription is removed because its push service
			 * reported it gone.
			 *
			 * @param Subscription $subscription The removed subscription.
			 * @param SendResult   $result       The result that triggered removal.
			 */
			\do_action( 'upn_subscription_pruned', $subscription, $result );

			return;
		}

		$table = self::table();
		$now   = \current_time( 'mysql' );

		if ( $result->success ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}`
					 SET total_sent_success_notifications = total_sent_success_notifications + 1,
					     last_success_on = %s,
					     last_error = NULL
					 WHERE id = %d",
					$now,
					$subscription->id
				)
			);
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET total_sent_fail_notifications = total_sent_fail_notifications + 1,
				     last_error = %s
				 WHERE id = %d",
				self::clip( $result->summary(), 191 ),
				$subscription->id
			)
		);
	}

	/**
	 * Note that a device checked in (page load with an active subscription).
	 *
	 * @param int $id
	 * @return void
	 */
	public static function touch( $id ) {
		global $wpdb;

		$wpdb->update(
			self::table(),
			array( 'last_seen_on' => \current_time( 'mysql' ) ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Stamp the subscription behind an endpoint as having clicked a
	 * notification just now. Returns the row id, or 0 when unknown.
	 *
	 * @param string $endpoint
	 * @return int
	 */
	public static function touch_click( $endpoint ) {
		global $wpdb;

		$subscription = self::find_by_endpoint( (string) $endpoint );
		if ( ! $subscription ) {
			return 0;
		}

		$wpdb->update(
			self::table(),
			array( 'last_click_on' => \current_time( 'mysql' ) ),
			array( 'id' => (int) $subscription->id ),
			array( '%s' ),
			array( '%d' )
		);

		return (int) $subscription->id;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Is this a URL a push service would issue?
	 *
	 * Browsers only hand out https endpoints, so anything else is either a
	 * bug or an attempt to make the server POST somewhere it should not.
	 *
	 * @param string $endpoint
	 * @return bool
	 */
	public static function is_acceptable_endpoint( $endpoint ) {
		if ( '' === $endpoint || \strlen( $endpoint ) > 2048 ) {
			return false;
		}

		$parts = \wp_parse_url( $endpoint );

		if ( empty( $parts['scheme'] ) || 'https' !== \strtolower( $parts['scheme'] ) ) {
			return false;
		}

		if ( empty( $parts['host'] ) ) {
			return false;
		}

		/**
		 * Filter whether a push endpoint host is acceptable.
		 *
		 * Defaults to allowing any https host, because the set of push services
		 * is open-ended (every browser vendor runs its own). A site that wants
		 * to pin to known services can do so here.
		 *
		 * @param bool   $allowed
		 * @param string $host
		 * @param string $endpoint
		 */
		return (bool) \apply_filters( 'upn_endpoint_host_allowed', true, $parts['host'], $endpoint );
	}

	/**
	 * Truncate a string to a column width without splitting a multibyte char.
	 *
	 * @param string $value
	 * @param int    $length
	 * @return string
	 */
	private static function clip( $value, $length ) {
		$value = (string) $value;
		if ( \function_exists( 'mb_substr' ) ) {
			return \mb_substr( $value, 0, $length );
		}
		return \substr( $value, 0, $length );
	}

	/**
	 * A WP_Error describing the last database failure.
	 *
	 * @return \WP_Error
	 */
	private static function db_error() {
		global $wpdb;

		return new \WP_Error(
			'upn_db_error',
			$wpdb->last_error
				? $wpdb->last_error
				: \__( 'The subscription could not be saved.', 'ultimate-push-notifications' )
		);
	}

}
