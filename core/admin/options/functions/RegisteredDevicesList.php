<?php namespace UltimatePushNotifications\admin\options\functions;

/**
 * Class: Coin LIst
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\Masking;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}



class RegisteredDevicesList extends \WP_List_Table {
	var $item_per_page = 10;
	var $total_post;

	private $all_count_link;
	private $get_only_my_devices;

	public function __construct( $all_count_link, $get_only_my_devices = false ) {
		parent::__construct(
			array(
				'singular' => __( 'Registered Device', 'ultimate-push-notifications' ),
				'plural'   => __( 'Registered Devices', 'ultimate-push-notifications' ),
				'ajax'     => false,
			)
		);

		$this->all_count_link      = $all_count_link;
		$this->get_only_my_devices = $get_only_my_devices;
	}

	/**
	 *
	 * @return typeGenerate column
	 */
	public function get_columns() {
		return array(
			'cb'                       => '<input type="checkbox" />',
			'token'                    => __( 'Token', 'ultimate-push-notifications' ),
			'registered_by'            => __( 'Registered By', 'ultimate-push-notifications' ),
			'total_notifications_sent' => __( 'Total Notifications Sent', 'ultimate-push-notifications' ),
			'registered_on'            => __( 'Registered On', 'ultimate-push-notifications' ),
		);
	}



	/**
	 * Column default info
	 */
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'token':
			case 'registered_by':
			case 'registered_on':
			case 'total_notifications_sent':
				return $item->{$column_name};
			default:
				return '---'; // Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Column cb
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="id[]" value="%1$s" />', $item->id );
	}


	public function column_token( $item ) {
		$content  = \substr( $item->token, 0, 30 );
		$content .= '<div class="row-actions"><span class="edit">';
		$content .= sprintf( __( '%1$sSend Test Notification%2$s', 'ultimate-push-notifications' ), '<a class="send-test-notifications" data-token = "' . $item->token . '" >', '</a>' );
		$content .= '</span></div>';
		return $content;
	}

	public function column_registered_by( $item ) {
		if ( isset( $item->user_id ) ) {
			$user = get_user_by( 'id', $item->user_id );
			return $user->user_login . ' ( <i>' . $user->user_email . '</i> )';
		}
		return;
	}

	public function column_registered_on( $item ) {
		$dt  = date( 'd M Y', strtotime( $item->registered_on ) );
		$dt .= ' at ' . date( ' h:i A', strtotime( $item->registered_on ) );
		return $dt;
	}

	public function column_total_notifications_sent( $item ) {
		$content  = __( 'Success :', 'ultimate-push-notifications' );
		$content .= empty( $item->total_sent_success_notifications ) ? 0 : $item->total_sent_success_notifications;
		$content .= sprintf( __( '%sFailure :', 'ultimate-push-notifications' ), '<br/>' );
		$content .= empty( $item->total_sent_fail_notifications ) ? 0 : $item->total_sent_fail_notifications;
		return $content;
	}

	public function no_items() {
		return _e( 'Sorry! No Registered Device Found!', 'ultimate-push-notifications' );
	}

	function get_views() {
		$all_link     = admin_url( 'admin.php?page=' . $this->all_count_link );
		$views['all'] = sprintf( __( '%1$sAll%2$s', 'ultimate-push-notifications' ), "<a href='{$all_link}' >", "<span class='count'>({$this->total_post})</span></a>" );
		return $views;
	}

	public function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'ultimate-push-notifications' ),
		);
		return $actions;
	}

	/**
	 * Get the data
	 *
	 * @global type $wpdb
	 * @return type
	 */
	private function poulate_the_data() {
		global $wpdb;
		$search = '';
		if ( isset( $_GET['s'] ) && ! empty( $skey = Util::check_evil_script( $_GET['s'] ) ) ) {
			$search = " where c.token like '%{$skey}%' or c.total_sent_success_notifications like '%{$skey}%' or c.total_sent_fail_notifications like '%{$skey}%'";
		}

		if ( isset( $_GET['order'] ) && ! empty( $order = Util::check_evil_script( $_GET['order'] ) ) ) {
			$order = $order;
		} else {
			$order = 'c.id DESC';
		}

		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
				$offset = $this->item_per_page * ( $current_page - 1 );
		} else {
				$offset = 0;
		}

		// check all list or my devices
		if ( true === $this->get_only_my_devices ) {
			$current_user_id = Util::cs_current_user_id();
			if ( empty( $search ) ) {
				$search = " where c.user_id = {$current_user_id}";
			} else {
				$search .= " and c.user_id = {$current_user_id}";
			}
		}

		$data   = array();
		$result = $wpdb->get_results(
			"SELECT * from {$wpdb->prefix}upn_user_devices as c "
				. "$search "
				. " order by {$order} limit $this->item_per_page offset {$offset}"
		);

		if ( $result ) {
			foreach ( $result as $item ) {
				$data[] = $item;
			}
		}
		$total         = $wpdb->get_var( "select count(id) as total from {$wpdb->prefix}upn_user_devices as c {$search} " );
		$data['count'] = $this->total_post = $total;

		return $data;
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
					$log_ids = Util::check_evil_script( $_GET['id'] );
					if ( $log_ids ) {
						foreach ( $log_ids as $log ) {
							$wpdb->delete( "{$wpdb->prefix}upn_user_devices", array( 'id' => $log ) );
						}
					}
					$this->success_admin_notice();
					break;
			endswitch;
		}
		return;
	}

	public function success_admin_notice() {
		?>
		<div class="updated">
			<p><?php _e( 'Device has been deleted successfully!', 'ultimate-push-notifications' ); ?></p>
		</div>
		<?php
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		// Column headers
		$this->_column_headers = array( $columns, $hidden, $sortable = '' );
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

}
