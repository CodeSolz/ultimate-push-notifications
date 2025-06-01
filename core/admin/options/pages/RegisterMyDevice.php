<?php namespace UltimatePushNotifications\admin\options\pages;

/**
 * Class: Register Device
 *
 * @package Options
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\builders\FormBuilder;
use UltimatePushNotifications\admin\builders\AdminPageBuilder;
use UltimatePushNotifications\admin\options\functions\RegisteredDevicesList;


class RegisterMyDevice {

	/**
	 * Hold page generator class
	 *
	 * @var type
	 */
	private $Admin_Page_Generator;

	/**
	 * Form Generator
	 *
	 * @var type
	 */
	private $Form_Generator;


	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();

	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_page( $args, $option ) {

		// pre_print( $option );

		$page                       = isset( $_GET['page'] ) ? Util::check_evil_script( $_GET['page'] ) : '';
		$args['well_search_result'] = '';
		if ( isset( $_GET['s'] ) && ! empty( $sfor = Util::cs_esc_html( $_GET['s'] ) ) ) {
			$args['well_search_result'] = sprintf(
				__( '%1$s Search results for : %2$s%3$s %4$s << Back to all%5$s', 'ultimate-push-notifications' ),
				"<p class='search-keyword'>",
				"<b> {$sfor} </b>",
				'</p>',
				'<a href="' . Util::cs_generate_admin_url( $page ) . '" class="button">',
				'</a>'
			);
		}

		// check app file exists
		$is_app_file_exists = false;
		if ( ! file_exists( CS_UPN_BASE_DIR_PATH . 'assets/plugins/firebase/js/firebaseInit.min.js' ) ) {
			$is_app_file_exists = sprintf(
				__( 'You need to update your app configuration. Please %1$sgo to app config%2$s and update your configuration.', 'ultimate-push-notifications' ),
				'<a href="' . admin_url( 'admin.php?page=cs-upn-app-configuration' ) . '">',
				'</a>'
			);
		}

		ob_start();
		$adCodeList = new RegisteredDevicesList( 'cs-upn-register-my-device', true );
		$adCodeList->prepare_items();
		echo '<form id="plugins-filter" method="get"><input type="hidden" name="page" value="' . $page . '" />';
		$adCodeList->views();
		$adCodeList->search_box( __( 'Search', 'ultimate-push-notifications' ), '' );
		$adCodeList->display();
		echo '</form>';
		$html = ob_get_clean();

		$args['content'] = false === $is_app_file_exists ? $html : $is_app_file_exists;

		$swal_title = '....';
		// $btn_txt              = '...';
		$update_hidden_fields = array();

		$hidden_fields = array_merge_recursive(
			array(
				'method'     => array(
					'id'    => 'method',
					'type'  => 'hidden',
					'value' => '....',
				),
				'swal_title' => array(
					'id'    => 'swal_title',
					'type'  => 'hidden',
					'value' => $swal_title,
				),
			),
			$update_hidden_fields
		);

		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['body_class'] = 'no-bottom-margin';


		$args['well'] = empty( $args['well_search_result'] ) ? sprintf(
			Util::upn_no_app_config_notification( $option ) .  
			'<ul>
				<li> <b> ' . __( 'Basic Hints', 'ultimate-push-notifications' ) . ' </b>
					<ol>
						<li>
							' . __( 'You should have SSL enabled to make the application work. ', 'ultimate-push-notifications' ) . '
						</li>
						<li>
							' . __( 'When the page loaded and notification permission appeared, you must click \'allow\' to allow notification. ', 'ultimate-push-notifications' ) . '
						</li>
						<li>
							' . __( 'If somehow you close the permission prompt window, %1$sClick here to see the notification permission%2$s prompt again.', 'ultimate-push-notifications' ) . '
						</li>
						<li>
							' . __(
								'If you don\'t see the prompt, click the lock icon located in-front of the URL in the browser. You will be able to see the site information. 
							From there, select \'Ask(default)\' value for the Notifications.',
								'ultimate-push-notifications'
							) . '
						</li>
					</ol>
				</li>
			</ul>',
			'<a href="' . admin_url( 'admin.php?page=cs-upn-register-my-device' ) . '">',
			'</a>'
		) : $args['well_search_result'];

		return $this->Admin_Page_Generator->generate_page( $args );
	}

}


