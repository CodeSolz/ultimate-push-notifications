<?php namespace UltimatePushNotifications\admin\options\functions;

/**
 * The subscriber list.
 *
 * One table serves two screens: "All Registered Devices" (administrators,
 * every row) and "Register My Device" (any user, only their own rows). Every
 * value that reaches SQL is either bound through $wpdb->prepare() or resolved
 * against a whitelist; nothing from the request is interpolated directly.
 *
 * @package Admin
 * @since 1.0.0
 * @since 1.6.0 Shows browser, OS, device, locale, timezone, last seen, last
 *              delivery and consent — the metadata segmentation needs, visible
 *              here so the value of filtering on it is obvious.
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\transport\Subscription;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


class RegisteredDevicesList extends \WP_List_Table {
	var $item_per_page = 20;
	var $total_post;

	private $all_count_link;
	private $get_only_my_devices;

	/** @var array<string,int> Row counts per view, filled by populate. */
	private $view_counts = array();

	public function __construct( $all_count_link, $get_only_my_devices = false ) {
		parent::__construct(
			array(
				'singular' => __( 'Subscriber', 'ultimate-push-notifications' ),
				'plural'   => __( 'Subscribers', 'ultimate-push-notifications' ),
				'ajax'     => false,
			)
		);

		$this->all_count_link      = $all_count_link;
		$this->get_only_my_devices = $get_only_my_devices;
	}

	/**
	 * Human labels for the vocabulary UserAgent::parse() emits.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'chrome'   => 'Chrome',
			'firefox'  => 'Firefox',
			'safari'   => 'Safari',
			'edge'     => 'Edge',
			'opera'    => 'Opera',
			'samsung'  => 'Samsung Internet',
			'brave'    => 'Brave',
			'vivaldi'  => 'Vivaldi',
			'windows'  => 'Windows',
			'macos'    => 'macOS',
			'ios'      => 'iOS',
			'ipados'   => 'iPadOS',
			'android'  => 'Android',
			'linux'    => 'Linux',
			'chromeos' => 'ChromeOS',
			'desktop'  => __( 'Desktop', 'ultimate-push-notifications' ),
			'mobile'   => __( 'Mobile', 'ultimate-push-notifications' ),
			'tablet'   => __( 'Tablet', 'ultimate-push-notifications' ),
		);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public static function label( $key ) {
		$key    = (string) $key;
		$labels = self::labels();
		return isset( $labels[ $key ] ) ? $labels[ $key ] : ( '' === $key ? '' : \ucfirst( $key ) );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'subscriber' => __( 'Subscriber', 'ultimate-push-notifications' ),
			'who'        => __( 'Who', 'ultimate-push-notifications' ),
			'locale'     => __( 'Locale', 'ultimate-push-notifications' ),
			'activity'   => __( 'Activity', 'ultimate-push-notifications' ),
			'delivered'  => __( 'Delivered', 'ultimate-push-notifications' ),
			'subscribed' => __( 'Subscribed', 'ultimate-push-notifications' ),
		);

		if ( $this->get_only_my_devices ) {
			unset( $columns['who'] );
		}

		return $columns;
	}

	/**
	 * Column → SQL column. Only names listed here can reach ORDER BY.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'subscriber' => array( 'browser', false ),
			'locale'     => array( 'locale', false ),
			'activity'   => array( 'last_seen_on', true ),
			'delivered'  => array( 'total_sent_success_notifications', true ),
			'subscribed' => array( 'registered_on', true ),
		);
	}

	/**
	 * @return array
	 */
	protected function get_default_primary_column_name() {
		return 'subscriber';
	}

	/**
	 * Column default info
	 */
	function column_default( $item, $column_name ) {
		return '—';
	}

	/**
	 * Column cb
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="id[]" value="%1$s" />', (int) $item->id );
	}

	/**
	 * Browser · OS · device, with the push service underneath and the test-send
	 * action. Legacy FCM rows are flagged: that transport has been shut down
	 * since July 2024 and the row will be pruned on its first send.
	 */
	public function column_subscriber( $item ) {
		$browser = self::label( isset( $item->browser ) ? $item->browser : '' );
		$os      = self::label( isset( $item->os ) ? $item->os : '' );
		$device  = self::label( isset( $item->device ) ? $item->device : '' );

		$parts = \array_filter( array( $browser, $os ) );
		$main  = $parts ? \implode( ' · ', $parts ) : __( 'Unknown browser', 'ultimate-push-notifications' );

		$content = '<strong>' . \esc_html( $main ) . '</strong>';
		if ( '' !== $device ) {
			$content .= ' <span class="upn-badge">' . \esc_html( $device ) . '</span>';
		}

		$transport = isset( $item->transport ) ? $item->transport : Subscription::TRANSPORT_FCM_LEGACY;
		if ( Subscription::TRANSPORT_WEBPUSH === $transport ) {
			$host = '';
			if ( ! empty( $item->endpoint ) ) {
				$parts = \wp_parse_url( $item->endpoint );
				$host  = isset( $parts['host'] ) ? $parts['host'] : '';
			}
			$content .= '<br/><span class="description">' . \esc_html( $host ) . '</span>';
		} else {
			$content .= '<br/><span class="upn-badge upn-badge--warn" title="' . \esc_attr__( 'Registered through the legacy Firebase API, which Google shut down in July 2024. The visitor must subscribe again.', 'ultimate-push-notifications' ) . '">' . \esc_html__( 'Legacy FCM', 'ultimate-push-notifications' ) . '</span>';
		}

		$actions = array(
			'test' => sprintf(
				'<a href="#" class="send-test-notifications" data-device-id="%d"%s>%s</a>',
				(int) $item->id,
				Subscription::TRANSPORT_WEBPUSH === $transport ? '' : ' data-token="' . \esc_attr( (string) $item->token ) . '"',
				\esc_html__( 'Send test notification', 'ultimate-push-notifications' )
			),
		);

		if ( ! empty( $item->user_agent ) ) {
			$actions['ua'] = '<span title="' . \esc_attr( $item->user_agent ) . '">' . \esc_html__( 'User agent', 'ultimate-push-notifications' ) . '</span>';
		}

		return $content . $this->row_actions( $actions );
	}

	/**
	 * The account behind the row, or "Visitor" for an anonymous subscriber,
	 * with the page they subscribed on.
	 */
	public function column_who( $item ) {
		if ( empty( $item->user_id ) ) {
			$content = '<span class="upn-badge">' . \esc_html__( 'Visitor', 'ultimate-push-notifications' ) . '</span>';
		} else {
			$user = get_user_by( 'id', $item->user_id );
			if ( ! $user ) {
				// The account was deleted but its device row outlived it.
				$content = \esc_html( sprintf(
					/* translators: %d: user id */
					__( 'Deleted user (#%d)', 'ultimate-push-notifications' ),
					(int) $item->user_id
				) );
			} else {
				$content = '<strong>' . \esc_html( $user->user_login ) . '</strong><br/><span class="description">' . \esc_html( $user->user_email ) . '</span>';
			}
		}

		if ( ! empty( $item->consent_source ) ) {
			$path     = \wp_parse_url( $item->consent_source, PHP_URL_PATH );
			$path     = ( null === $path || '' === $path ) ? '/' : $path;
			$content .= '<br/><span class="description" title="' . \esc_attr( $item->consent_source ) . '">' . \esc_html( sprintf(
				/* translators: %s: URL path */
				__( 'via %s', 'ultimate-push-notifications' ),
				\mb_strimwidth( $path, 0, 40, '…' )
			) ) . '</span>';
		}

		return $content;
	}

	/**
	 * Locale and timezone as the browser reported them.
	 */
	public function column_locale( $item ) {
		$locale = ! empty( $item->locale ) ? $item->locale : '';
		$tz     = ! empty( $item->timezone ) ? $item->timezone : '';

		if ( '' === $locale && '' === $tz ) {
			return '<span class="description">—</span>';
		}

		$content = '' !== $locale ? '<strong>' . \esc_html( $locale ) . '</strong>' : '';
		if ( '' !== $tz ) {
			$content .= ( '' !== $content ? '<br/>' : '' ) . '<span class="description">' . \esc_html( $tz ) . '</span>';
		}
		return $content;
	}

	/**
	 * Last seen, last successful delivery, and the most recent error.
	 */
	public function column_activity( $item ) {
		$lines = array();

		if ( ! empty( $item->last_seen_on ) ) {
			$lines[] = \esc_html( sprintf(
				/* translators: %s: relative time, e.g. "3 hours ago" */
				__( 'Seen %s', 'ultimate-push-notifications' ),
				self::ago( $item->last_seen_on )
			) );
		}

		if ( ! empty( $item->last_success_on ) ) {
			$lines[] = '<span class="description">' . \esc_html( sprintf(
				/* translators: %s: relative time */
				__( 'Delivered %s', 'ultimate-push-notifications' ),
				self::ago( $item->last_success_on )
			) ) . '</span>';
		}

		if ( ! empty( $item->last_error ) ) {
			$lines[] = '<span class="upn-error" title="' . \esc_attr( $item->last_error ) . '">' . \esc_html( \mb_strimwidth( $item->last_error, 0, 40, '…' ) ) . '</span>';
		}

		return $lines ? \implode( '<br/>', $lines ) : '<span class="description">—</span>';
	}

	/**
	 * Success / failure counts.
	 */
	public function column_delivered( $item ) {
		$ok   = empty( $item->total_sent_success_notifications ) ? 0 : (int) $item->total_sent_success_notifications;
		$fail = empty( $item->total_sent_fail_notifications ) ? 0 : (int) $item->total_sent_fail_notifications;

		$content = '<strong>' . \number_format_i18n( $ok ) . '</strong>';
		if ( $fail > 0 ) {
			$content .= ' <span class="upn-error">' . \esc_html( sprintf(
				/* translators: %s: count */
				__( '(%s failed)', 'ultimate-push-notifications' ),
				\number_format_i18n( $fail )
			) ) . '</span>';
		}
		return $content;
	}

	/**
	 * When they subscribed; the consent timestamp on hover.
	 */
	public function column_subscribed( $item ) {
		if ( empty( $item->registered_on ) ) {
			return '—';
		}

		$ts    = self::timestamp( $item->registered_on );
		$title = ! empty( $item->consent_on )
			? sprintf(
				/* translators: %s: date and time */
				__( 'Consent recorded %s', 'ultimate-push-notifications' ),
				\date_i18n( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), self::timestamp( $item->consent_on ) )
			)
			: '';

		return '<span title="' . \esc_attr( $title ) . '">' . \esc_html( \date_i18n( \get_option( 'date_format' ), $ts ) ) . '</span><br/><span class="description">' . \esc_html( self::ago( $item->registered_on ) ) . '</span>';
	}

	/**
	 * Stored datetimes are site-local (current_time( 'mysql' )); read them as such.
	 *
	 * @param string $mysql
	 * @return int
	 */
	private static function timestamp( $mysql ) {
		$ts = \strtotime( $mysql . ' UTC' );
		return false === $ts ? 0 : $ts;
	}

	/**
	 * @param string $mysql
	 * @return string
	 */
	private static function ago( $mysql ) {
		$then = self::timestamp( $mysql );
		$now  = (int) \current_time( 'timestamp' );
		if ( 0 === $then ) {
			return '';
		}
		if ( $then > $now ) {
			return __( 'just now', 'ultimate-push-notifications' );
		}
		return sprintf(
			/* translators: %s: human time difference */
			__( '%s ago', 'ultimate-push-notifications' ),
			\human_time_diff( $then, $now )
		);
	}

	public function no_items() {
		if ( $this->get_only_my_devices ) {
			\esc_html_e( 'This browser is not registered yet.', 'ultimate-push-notifications' );
		} else {
			\esc_html_e( 'No subscribers yet. Turn on the Subscribe Prompt or add the subscribe button to a page.', 'ultimate-push-notifications' );
		}
	}

	/**
	 * All / Logged-in / Visitors / Web Push / Legacy FCM, each with a count.
	 *
	 * @return array
	 */
	function get_views() {
		$base    = admin_url( 'admin.php?page=' . $this->all_count_link );
		$current = $this->current_view();
		$views   = array();

		$defs = array(
			'all'       => __( 'All', 'ultimate-push-notifications' ),
			'logged_in' => __( 'Logged-in', 'ultimate-push-notifications' ),
			'anonymous' => __( 'Visitors', 'ultimate-push-notifications' ),
			'webpush'   => __( 'Web Push', 'ultimate-push-notifications' ),
			'legacy'    => __( 'Legacy FCM', 'ultimate-push-notifications' ),
		);

		if ( $this->get_only_my_devices ) {
			$defs = array( 'all' => $defs['all'] );
		}

		foreach ( $defs as $key => $label ) {
			$count = isset( $this->view_counts[ $key ] ) ? (int) $this->view_counts[ $key ] : 0;
			if ( 'all' !== $key && 0 === $count ) {
				continue;
			}
			$url   = 'all' === $key ? $base : \add_query_arg( 'view', $key, $base );
			$class = $key === $current ? ' class="current"' : '';
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				\esc_url( $url ),
				$class,
				\esc_html( $label ),
				\number_format_i18n( $count )
			);
		}

		return $views;
	}

	/**
	 * @return string One of all|logged_in|anonymous|webpush|legacy.
	 */
	private function current_view() {
		if ( $this->get_only_my_devices ) {
			return 'all'; // The links are not offered there; the parameter must not narrow own rows to nothing.
		}
		$view = isset( $_GET['view'] ) ? \sanitize_key( \wp_unslash( $_GET['view'] ) ) : 'all';
		return \in_array( $view, array( 'logged_in', 'anonymous', 'webpush', 'legacy' ), true ) ? $view : 'all';
	}

	public function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'ultimate-push-notifications' ),
		);
		return $actions;
	}

	/**
	 * The WHERE fragment for a view, as a SQL clause on alias c and its params.
	 *
	 * @param string $view
	 * @return array{0:string,1:array}
	 */
	private function view_clause( $view ) {
		switch ( $view ) {
			case 'logged_in':
				return array( 'c.user_id > 0', array() );
			case 'anonymous':
				return array( '( c.user_id IS NULL OR c.user_id = 0 )', array() );
			case 'webpush':
				return array( 'c.transport = %s', array( Subscription::TRANSPORT_WEBPUSH ) );
			case 'legacy':
				return array( 'c.transport = %s', array( Subscription::TRANSPORT_FCM_LEGACY ) );
		}
		return array( '', array() );
	}

	/**
	 * Get the data
	 *
	 * @global type $wpdb
	 * @return type
	 */
	private function poulate_the_data() {
		global $wpdb;

		/**
		 * Every value that reaches SQL below is either bound through
		 * $wpdb->prepare() or resolved against a whitelist. Nothing from $_GET is
		 * interpolated into the statement directly.
		 */
		$clauses = array();
		$params  = array();

		if ( isset( $_GET['s'] ) ) {
			$skey = \sanitize_text_field( \wp_unslash( $_GET['s'] ) );
			if ( '' !== $skey ) {
				$like      = '%' . $wpdb->esc_like( $skey ) . '%';
				$clauses[] = '( c.endpoint LIKE %s OR c.token LIKE %s OR c.browser LIKE %s OR c.os LIKE %s OR c.device LIKE %s OR c.locale LIKE %s OR c.timezone LIKE %s OR c.user_agent LIKE %s OR c.consent_source LIKE %s )';
				$params    = \array_merge( $params, \array_fill( 0, 9, $like ) );
			}
		}

		// check all list or my devices
		if ( true === $this->get_only_my_devices ) {
			$clauses[] = 'c.user_id = %d';
			$params[]  = \get_current_user_id();
		}

		// The scope every view is counted within (search + ownership), before the view itself.
		$scope_where  = $clauses ? ' WHERE ' . \implode( ' AND ', $clauses ) : '';
		$scope_params = $params;

		list( $view_sql, $view_params ) = $this->view_clause( $this->current_view() );
		if ( '' !== $view_sql ) {
			$clauses[] = $view_sql;
			$params    = \array_merge( $params, $view_params );
		}

		$where = $clauses ? ' WHERE ' . \implode( ' AND ', $clauses ) : '';

		// ORDER BY cannot be bound as a parameter, so resolve it against a whitelist.
		$sortable_columns = array(
			'id',
			'user_id',
			'browser',
			'os',
			'locale',
			'registered_on',
			'last_seen_on',
			'total_sent_success_notifications',
			'total_sent_fail_notifications',
		);

		$orderby = 'id';
		if ( isset( $_GET['orderby'] ) ) {
			$requested = \sanitize_key( \wp_unslash( $_GET['orderby'] ) );
			if ( \in_array( $requested, $sortable_columns, true ) ) {
				$orderby = $requested;
			}
		}

		$order_dir = 'DESC';
		if ( isset( $_GET['order'] ) && 'asc' === \strtolower( \sanitize_key( \wp_unslash( $_GET['order'] ) ) ) ) {
			$order_dir = 'ASC';
		}

		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
				$offset = $this->item_per_page * ( $current_page - 1 );
		} else {
				$offset = 0;
		}

		$data = array();

		// A secondary key keeps paging stable when the primary sort has ties (most rows share a browser).
		$sql          = "SELECT * FROM {$wpdb->prefix}upn_user_devices AS c{$where} ORDER BY c.{$orderby} {$order_dir}, c.id DESC LIMIT %d OFFSET %d";
		$sql_params   = \array_merge( $params, array( $this->item_per_page, $offset ) );
		$result       = $wpdb->get_results( $wpdb->prepare( $sql, $sql_params ) );

		if ( $result ) {
			foreach ( $result as $item ) {
				$data[] = $item;
			}
		}

		$count_sql = "SELECT COUNT(id) FROM {$wpdb->prefix}upn_user_devices AS c{$where}";
		$total     = empty( $params )
			? $wpdb->get_var( $count_sql )
			: $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$data['count'] = $this->total_post = (int) $total;

		$this->view_counts = $this->count_views( $scope_where, $scope_params );

		return $data;
	}

	/**
	 * One query for every view's count within the current scope.
	 *
	 * @param string $scope_where
	 * @param array  $scope_params
	 * @return array<string,int>
	 */
	private function count_views( $scope_where, $scope_params ) {
		global $wpdb;

		$sql = "SELECT COUNT(id) AS total,
			SUM( CASE WHEN c.user_id > 0 THEN 1 ELSE 0 END ) AS logged_in,
			SUM( CASE WHEN c.user_id IS NULL OR c.user_id = 0 THEN 1 ELSE 0 END ) AS anonymous,
			SUM( CASE WHEN c.transport = %s THEN 1 ELSE 0 END ) AS webpush,
			SUM( CASE WHEN c.transport = %s THEN 1 ELSE 0 END ) AS legacy
			FROM {$wpdb->prefix}upn_user_devices AS c{$scope_where}";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, \array_merge( array( Subscription::TRANSPORT_WEBPUSH, Subscription::TRANSPORT_FCM_LEGACY ), $scope_params ) ), ARRAY_A );

		return array(
			'all'       => isset( $row['total'] ) ? (int) $row['total'] : 0,
			'logged_in' => isset( $row['logged_in'] ) ? (int) $row['logged_in'] : 0,
			'anonymous' => isset( $row['anonymous'] ) ? (int) $row['anonymous'] : 0,
			'webpush'   => isset( $row['webpush'] ) ? (int) $row['webpush'] : 0,
			'legacy'    => isset( $row['legacy'] ) ? (int) $row['legacy'] : 0,
		);
	}

	function process_bulk_action() {
		global $wpdb;
		  // security check!
		if ( isset( $_GET['_wpnonce'] ) && ! empty( $_wpnonce = Util::check_evil_script( $_GET['_wpnonce'] ) ) ) {

			$action = 'bulk-' . $this->_args['plural'];

			if ( ! wp_verify_nonce( $_wpnonce, $action ) ) {
				wp_die( __( 'Nope! Security check failed!', 'ultimate-push-notifications' ) );
			}

			$action = $this->current_action();

			switch ( $action ) :
				case 'delete':
					$log_ids = isset( $_GET['id'] ) ? (array) \wp_unslash( $_GET['id'] ) : array();
					$log_ids = \array_filter( \array_map( 'absint', $log_ids ) );

					/**
					 * "Register My Device" is reachable by any user who can read, so a
					 * non-administrator may only delete rows they own. Without the
					 * user_id constraint any subscriber could delete another account's
					 * device by guessing its row id.
					 */
					$can_manage_all = \current_user_can( 'manage_options' );
					$current_user   = \get_current_user_id();

					foreach ( $log_ids as $log ) {
						$where = array( 'id' => $log );
						if ( ! $can_manage_all ) {
							$where['user_id'] = $current_user;
						}
						$wpdb->delete( "{$wpdb->prefix}upn_user_devices", $where );
					}

					$this->success_admin_notice( \count( $log_ids ) );
					break;
			endswitch;
		}
		return;
	}

	public function success_admin_notice( $count = 1 ) {
		?>
		<div class="updated">
			<p><?php echo \esc_html( sprintf( _n( '%s subscriber deleted.', '%s subscribers deleted.', $count, 'ultimate-push-notifications' ), \number_format_i18n( $count ) ) ); ?></p>
		</div>
		<?php
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		// Column headers
		$this->_column_headers = array( $columns, $hidden, $sortable, 'subscriber' );
		$this->process_bulk_action();

		$data  = $this->poulate_the_data();
		$count = $data['count'];
		unset( $data['count'] );
		$this->items = $data;

		 // Set the pagination
		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $this->item_per_page,
				'total_pages' => ceil( $count / $this->item_per_page ),
			)
		);
	}

	/**
	 * The little styling the columns need, printed once with the table.
	 *
	 * @return void
	 */
	public static function inline_styles() {
		?>
		<style>
			.upn-badge{display:inline-block;padding:0 6px;border-radius:3px;background:#f0f0f1;color:#3c434a;font-size:11px;line-height:18px;vertical-align:middle}
			.upn-badge--warn{background:#fcf0e4;color:#8a4b00}
			.upn-error{color:#b32d2e}
			.wp-list-table .column-subscriber{width:24%}
			.wp-list-table .column-who{width:20%}
			.wp-list-table .column-locale{width:12%}
			.wp-list-table .column-delivered{width:10%}
		</style>
		<?php
	}

}
