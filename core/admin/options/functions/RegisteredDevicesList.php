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

		$this->all_count_link = $all_count_link;
		$this->get_only_my_devices = $get_only_my_devices;
	}

	/**
	 *
	 * @return typeGenerate column
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'device_id'             => __( 'Device ID', 'ultimate-push-notifications' ),
			'token'          => __( 'Token', 'ultimate-push-notifications' ),
			'total_notifications_sent' => __( 'Total Notifications Sent', 'ultimate-push-notifications' ),
			'registered_on'             => __( 'Registered On', 'ultimate-push-notifications' ),
		);
	}

	/**
	 * Column default info
	 */
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'device_id':
			case 'token':
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

	public function column_device_id( $item ) {
		$content = $item->device_id;
		$content .= '<div class="row-actions"><span class="edit">';
		$content .= '<a class="send-test-notifications" data-token = "'.$item->token.'" >Send Test Notification</a>';
		$content .= '</span></div>';
		return $content;
	}

	public function column_token( $item ) {
		return $item->token;
	}

	public function column_registered_on( $item ) {
		return date('d M Y', strtotime($item->registered_on));
	}

	public function column_total_notifications_sent( $item ) {
		$content = 'Success : ' . empty( $item->total_sent_success_notifications ) ? 0 : $item->total_sent_success_notifications;
		$content .= '<br>Fail : ' . empty( $item->total_sent_fail_notifications ) ? 0 : $item->total_sent_fail_notifications;
		return $content;
	}
	
	public function no_items() {
		return _e( 'Sorry! You haven\'t Registered Any Device Yet!', 'ultimate-push-notifications' );
	}

	function get_views() {
		$all_link     = admin_url( 'admin.php?page=' . $this->all_count_link );
		$views['all'] = "<a href='{$all_link}' >All <span class='count'>({$this->total_post})</span></a>";
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
		global $wpdb, $wapg_tables;
		$search = '';
		if ( isset( $_GET['s'] ) && ! empty( $skey = $_GET['s'] ) ) {
			$search = " where c.find like '%{$skey}%'";
		}

		if ( isset( $_GET['order'] ) ) {
			$order = $_GET['order'];
		} else {
			$order = 'c.id DESC';
		}

		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
				$offset = $this->item_per_page * ( $current_page - 1 );
		} else {
				$offset = 0;
		}

		//check all list or my devices
		if( true === $this->get_only_my_devices ){
			$current_user_id = Util::cs_current_user_id();
			if( empty( $search ) ){
				$search = " where user_id = {$current_user_id}";
			}else{
				$search .= " and user_id = {$current_user_id}";
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
		global $wpdb, $wapg_tables;
		  // security check!
		if ( isset( $_GET['_wpnonce'] ) && ! empty( $_GET['_wpnonce'] ) ) {

			$action = 'bulk-' . $this->_args['plural'];

			if ( ! wp_verify_nonce( $_GET['_wpnonce'], $action ) ) {
				wp_die( 'Nope! Security check failed!' );
			}

			$action = $this->current_action();

			switch ( $action ) :
				case 'delete':
					$log_ids = $_GET['id'];
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
