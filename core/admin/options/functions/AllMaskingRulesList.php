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



class AllMaskingRulesList extends \WP_List_Table {
	var $item_per_page = 10;
	var $total_post;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'find_replace', 'ultimate-push-notifications' ),
				'plural'   => __( 'finds_replaces', 'ultimate-push-notifications' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 *
	 * @return typeGenerate column
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'find'             => __( 'Find', 'ultimate-push-notifications' ),
			'replace'          => __( 'Replace by', 'ultimate-push-notifications' ),
			'type'             => __( 'Rule Type', 'ultimate-push-notifications' ),
			'where_to_replace' => __( 'Where to replace', 'ultimate-push-notifications' ),
		);
	}

	/**
	 * Column default info
	 */
	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'find':
			case 'replace':
			case 'type':
			case 'where_to_replace':
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

	public function column_find( $item ) {
		echo Util::cs_esc_html( $item->find );
		$edit_link = admin_url( "admin.php?page=cs-add-replacement-rule&action=update&rule_id={$item->id}" );
		echo '<div class="row-actions"><span class="edit">';
		echo '<a href="' . $edit_link . '">Edit</a>';
		echo '</span></div>';
	}

	public function column_replace( $item ) {
		echo Util::cs_esc_html( $item->replace );
	}

	public function column_where_to_replace( $item ) {
		// pre_print( $item );
		if ( strtolower( $item->where_to_replace ) == 'all' ) {
			return 'All over the website';
		}
	}


	public function no_items() {
		_e( 'Sorry! No Rule Found!', 'ultimate-push-notifications' );
	}

	function get_views() {
		$all_link     = admin_url( 'admin.php?page=cs-all-masking-rules' );
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

		$data   = array();
		$result = $wpdb->get_results(
			"SELECT * from {$wpdb->prefix}rtafar_rules as c "
				. "$search "
				. " order by {$order} limit $this->item_per_page offset {$offset}"
		);

		if ( $result ) {
			foreach ( $result as $item ) {
				$data[] = $item;
			}
		}
		$total         = $wpdb->get_var( "select count(id) as total from {$wpdb->prefix}rtafar_rules as c {$search} " );
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
							$wpdb->delete( "{$wpdb->prefix}rtafar_rules", array( 'id' => $log ) );
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
			<p><?php _e( 'Rule has been deleted successfully!', 'ultimate-push-notifications' ); ?></p>
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
