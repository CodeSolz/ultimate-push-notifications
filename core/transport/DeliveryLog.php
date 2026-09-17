<?php namespace UltimatePushNotifications\transport;

/**
 * Delivery history.
 *
 * One row per notification sent — a batch to N recipients is one row with the
 * counts rolled up. The table existed since 1.3.0 with nothing writing to it;
 * this is what the health check reads to answer "when did a notification last
 * actually arrive, and how often do they fail".
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class DeliveryLog {

	/**
	 * Rows older than this are pruned so the table cannot grow without bound.
	 *
	 * @var int
	 */
	const RETENTION_DAYS = 90;

	/**
	 * Fully-prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'upn_notification_log';
	}

	/**
	 * Record the outcome of one notification.
	 *
	 * @param string       $type    Event key, e.g. "productSold", or "test".
	 * @param array        $payload What was sent.
	 * @param SendResult[] $results One per recipient.
	 * @return int|false Row id.
	 */
	public static function record( $type, array $payload, array $results ) {
		global $wpdb;

		$success = 0;
		$fail    = 0;
		$pruned  = 0;
		$retry   = 0;
		$first_error = '';

		foreach ( $results as $result ) {
			if ( ! $result instanceof SendResult ) {
				continue;
			}
			if ( $result->success ) {
				$success++;
				continue;
			}
			$fail++;
			if ( $result->subscription_gone ) {
				$pruned++;
			} elseif ( $result->retryable ) {
				$retry++;
			}
			if ( '' === $first_error ) {
				$first_error = $result->error_message ? $result->error_message : $result->error_code;
			}
		}

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'notification_type' => \substr( (string) $type, 0, 100 ),
				'title'             => isset( $payload['title'] ) ? (string) $payload['title'] : '',
				'body'              => isset( $payload['body'] ) ? (string) $payload['body'] : '',
				'icon'              => isset( $payload['icon'] ) ? \substr( (string) $payload['icon'], 0, 500 ) : '',
				'image'             => isset( $payload['image'] ) ? \substr( (string) $payload['image'], 0, 500 ) : '',
				'click_action'      => isset( $payload['click_action'] ) ? \substr( (string) $payload['click_action'], 0, 500 ) : '',
				'recipients'        => \count( $results ),
				'success_count'     => $success,
				'fail_count'        => $fail,
				'pruned_count'      => $pruned,
				'retry_count'       => $retry,
				'first_error'       => \substr( $first_error, 0, 191 ),
				'sent_at'           => \current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a delivery has been logged.
		 *
		 * @param int          $id
		 * @param string       $type
		 * @param array        $payload
		 * @param SendResult[] $results
		 */
		\do_action( 'upn_delivery_logged', $id, $type, $payload, $results );

		return $id;
	}

	/**
	 * Open a row for a send whose results will arrive later, one job at a time.
	 *
	 * @param string $type
	 * @param array  $payload
	 * @param int    $recipients
	 * @return int|false Row id.
	 */
	public static function open( $type, array $payload, $recipients ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'notification_type' => \substr( (string) $type, 0, 100 ),
				'title'             => isset( $payload['title'] ) ? (string) $payload['title'] : '',
				'body'              => isset( $payload['body'] ) ? (string) $payload['body'] : '',
				'icon'              => isset( $payload['icon'] ) ? \substr( (string) $payload['icon'], 0, 500 ) : '',
				'image'             => isset( $payload['image'] ) ? \substr( (string) $payload['image'], 0, 500 ) : '',
				'click_action'      => isset( $payload['click_action'] ) ? \substr( (string) $payload['click_action'], 0, 500 ) : '',
				'recipients'        => (int) $recipients,
				'success_count'     => 0,
				'fail_count'        => 0,
				'pruned_count'      => 0,
				'retry_count'       => 0,
				'first_error'       => '',
				'sent_at'           => \current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Roll one delivery result into an open row.
	 *
	 * Counters are incremented in SQL so concurrent queue workers finishing
	 * jobs for the same send cannot lose each other's updates. A retryable
	 * result bumps retry_count and nothing else; the terminal outcome of that
	 * recipient lands on a later call.
	 *
	 * @param int        $log_id
	 * @param SendResult $result
	 * @return void
	 */
	public static function apply_result( $log_id, SendResult $result ) {
		global $wpdb;

		$log_id = (int) $log_id;
		if ( $log_id <= 0 ) {
			return;
		}

		$table = self::table();

		if ( $result->success ) {
			$wpdb->query(
				$wpdb->prepare( "UPDATE `{$table}` SET success_count = success_count + 1 WHERE id = %d", $log_id )
			);
			return;
		}

		if ( $result->retryable ) {
			$wpdb->query(
				$wpdb->prepare( "UPDATE `{$table}` SET retry_count = retry_count + 1 WHERE id = %d", $log_id )
			);
			return;
		}

		$error = \substr( $result->error_message ? $result->error_message : $result->error_code, 0, 191 );

		if ( $result->subscription_gone ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}`
					 SET fail_count = fail_count + 1, pruned_count = pruned_count + 1,
					     first_error = IF(first_error = '' OR first_error IS NULL, %s, first_error)
					 WHERE id = %d",
					$error,
					$log_id
				)
			);
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
				 SET fail_count = fail_count + 1,
				     first_error = IF(first_error = '' OR first_error IS NULL, %s, first_error)
				 WHERE id = %d",
				$error,
				$log_id
			)
		);
	}

	/**
	 * Add recipients to an open row — for sends that happen one person at a
	 * time over a day (a welcome step) but belong together in the log.
	 *
	 * @param int $log_id
	 * @param int $n
	 * @return void
	 */
	public static function add_recipients( $log_id, $n = 1 ) {
		global $wpdb;

		$log_id = (int) $log_id;
		$n      = (int) $n;
		if ( $log_id <= 0 || $n <= 0 ) {
			return;
		}
		$table = self::table();
		$wpdb->query(
			$wpdb->prepare( "UPDATE `{$table}` SET recipients = recipients + %d WHERE id = %d", $n, $log_id )
		);
	}

	/**
	 * One recipient was deliberately not sent to (a cap, a preference).
	 * Counted apart from failures: nothing went wrong.
	 *
	 * @param int $log_id
	 * @return void
	 */
	public static function apply_skip( $log_id ) {
		global $wpdb;

		$log_id = (int) $log_id;
		if ( $log_id <= 0 ) {
			return;
		}
		$table = self::table();
		$wpdb->query(
			$wpdb->prepare( "UPDATE `{$table}` SET skipped_count = skipped_count + 1 WHERE id = %d", $log_id )
		);
	}

	/**
	 * Most recent rows.
	 *
	 * @param int $limit
	 * @return object[]
	 */
	public static function recent( $limit = 20 ) {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . self::table() . '` ORDER BY sent_at DESC, id DESC LIMIT %d',
				\max( 1, (int) $limit )
			)
		);
	}

	/**
	 * Aggregate counts over a window, for the health check.
	 *
	 * @param int $days
	 * @return array{sends:int, recipients:int, success:int, fail:int, pruned:int, skipped:int, clicks:int, last_success:?string, last_send:?string}
	 */
	public static function stats( $days = 7 ) {
		global $wpdb;

		$since = \gmdate( 'Y-m-d H:i:s', \time() - ( \max( 1, (int) $days ) * DAY_IN_SECONDS ) );
		$table = self::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(id) AS sends,
				        COALESCE(SUM(recipients), 0) AS recipients,
				        COALESCE(SUM(success_count), 0) AS success,
				        COALESCE(SUM(fail_count), 0) AS fail,
				        COALESCE(SUM(pruned_count), 0) AS pruned,
				        COALESCE(SUM(skipped_count), 0) AS skipped,
				        COALESCE(SUM(click_count), 0) AS clicks,
				        MAX(sent_at) AS last_send
				 FROM `{$table}`
				 WHERE sent_at >= %s",
				$since
			)
		);

		$last_success = $wpdb->get_var(
			"SELECT MAX(sent_at) FROM `{$table}` WHERE success_count > 0"
		);

		return array(
			'sends'        => $row ? (int) $row->sends : 0,
			'recipients'   => $row ? (int) $row->recipients : 0,
			'success'      => $row ? (int) $row->success : 0,
			'fail'         => $row ? (int) $row->fail : 0,
			'pruned'       => $row ? (int) $row->pruned : 0,
			'skipped'      => $row ? (int) $row->skipped : 0,
			'clicks'       => $row ? (int) $row->clicks : 0,
			'last_send'    => $row && $row->last_send ? $row->last_send : null,
			'last_success' => $last_success ? $last_success : null,
		);
	}

	/**
	 * Delete rows beyond the retention window.
	 *
	 * @return int Rows removed.
	 */
	public static function prune() {
		global $wpdb;

		/**
		 * How long send history is kept, in days. 0 keeps it forever.
		 *
		 * @param int $days
		 */
		$days = (int) \apply_filters( 'upn_log_retention_days', self::RETENTION_DAYS );
		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff = \gmdate( 'Y-m-d H:i:s', \time() - ( $days * DAY_IN_SECONDS ) );

		$removed = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . self::table() . '` WHERE sent_at < %s',
				$cutoff
			)
		);

		return false === $removed ? 0 : (int) $removed;
	}

}
