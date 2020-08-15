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

			$args['content'] = '';
			$swal_title           = '....';
			$btn_txt              = __( 'Click To See Notification Prompt', 'ultimate-push-notifications' );
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

			$args['btn_text']   = $btn_txt;
			$args['show_btn']   = true;
			$args['body_class'] = 'no-bottom-margin';

			$args['well'] = "<ul>
            <li> <b>Basic Hints</b>
                <ol>
                    <li>
                        When the page loaded and notification permission appeared, you must click 'allow' to allow notification. 
                    </li>
                    <li>
                        If somehow you close the permission prompt window, click the following button you show it again.
                    </li>
                    <li>
                        If you don't see the prompt, click the lock icon located in-front of the URL in the browser. You will be able to see the site information.
                    </li>
                </ol>
            </li>
        </ul>";

			return $this->Admin_Page_Generator->generate_page( $args );
		}

	}

}
