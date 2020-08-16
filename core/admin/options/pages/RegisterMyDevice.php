<?php namespace UltimatePushNotifications\admin\options\pages;

/**
 * Class: App Configuration
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

if ( ! \class_exists( 'RegisterMyDevice' ) ) {

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

			$page = isset( $_GET['page'] ) ? Util::check_evil_script( $_GET['page'] ) : '';
			if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
				$back_url     = Util::cs_generate_admin_url( $page );
				$args['well'] = "<p class='search-keyword'>Search results for : '<b>" . $_GET['s'] . "</b>' </p> <a href='{$back_url}' class='button'><< Back to all</a> ";
			}

			ob_start();
			$adCodeList = new RegisteredDevicesList( 'cs-upn-register-my-device', true );
			$adCodeList->prepare_items();
			echo '<form id="plugins-filter" method="get"><input type="hidden" name="page" value="' . $page . '" />';
			$adCodeList->views();
			$adCodeList->search_box( __( 'Search Coin', 'real-time-auto-find-and-replace' ), '' );
			$adCodeList->display();
			echo '</form>';
			$html = ob_get_clean();

			$args['content'] = $html;
			$swal_title           = '....';
			// $btn_txt              = '...';
			$update_hidden_fields = array();

			$hidden_fields = array_merge_recursive(
				array(
					'method'     => array(
						'id'    => 'method',
						'type'  => 'hidden',
						'value' => "....",
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

			$args['well'] = sprintf( "<ul>
            <li> <b>Basic Hints</b>
                <ol>
                    <li>
                        When the page loaded and notification permission appeared, you must click 'allow' to allow notification. 
                    </li>
                    <li>
                        If somehow you close the permission prompt window, %sClick here to see the notification permission%s prompt again.
                    </li>
                    <li>
						If you don't see the prompt, click the lock icon located in-front of the URL in the browser. You will be able to see the site information. 
						From there, select 'Ask(default)' value for the Notifications.                    
					</li>
                </ol>
            </li>
        </ul>", '<a href="'.admin_url('admin.php?page=cs-upn-register-my-device').'">', '</a>' );

			return $this->Admin_Page_Generator->generate_page( $args );
		}

	}

}
