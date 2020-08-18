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

if ( ! \class_exists( 'AppConfig' ) ) {

	class AppConfig {

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

			$fields = array(
				'st1'                              => array(
					'type'     => 'section_title',
					'title'    => __( 'Web / Desktop push notifications setup', 'ultimate-push-notifications' ),
					'desc_tip' => __( 'Please get the following configuration data from your firebase application.', 'ultimate-push-notifications' ),
				),
				'cs_app_config[apiKey]'            => array(
					'title'       => __( 'API Key', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'apiKey', $option, '' ),
					'placeholder' => __( 'Please enter api key', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter api key from your firebase app configuration. e.g: AIzaSyBFgjh1LQRreqtI7..', 'ultimate-push-notifications' ),
				),
				'cs_app_config[authDomain]'        => array(
					'title'       => __( 'Auth Domain', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'authDomain', $option, '' ),
					'placeholder' => __( 'Please enter auth domain', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter auth domain from your firebase app configuration. e.g: test-web-push-c2s99.firebaseapp.com', 'ultimate-push-notifications' ),
				),
				'cs_app_config[databaseURL]'       => array(
					'title'       => __( 'Database URL', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'required'    => true,
					'class'       => 'form-control',
					'value'       => FormBuilder::get_value( 'databaseURL', $option, '' ),
					'placeholder' => __( 'Please enter database url', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter database url from your firebase app configuration. e.g: https://test-web-push-c2s99.firebaseio.com', 'ultimate-push-notifications' ),
				),
				'cs_app_config[projectId]'         => array(
					'title'       => __( 'Project ID', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'projectId', $option, '' ),
					'placeholder' => __( 'Please enter project id', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter project id from your firebase app configuration. e.g: test-web-push-c2s99', 'ultimate-push-notifications' ),
				),
				'cs_app_config[storageBucket]'     => array(
					'title'       => __( 'Storage Bucket', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'storageBucket', $option, '' ),
					'placeholder' => __( 'Please enter storage bucket', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter storage bucket from your firebase app configuration. e.g: test-web-push-c2s99.appspot.com', 'ultimate-push-notifications' ),
				),
				'cs_app_config[messagingSenderId]' => array(
					'title'       => __( 'Messaging Sender ID', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'messagingSenderId', $option, '' ),
					'placeholder' => __( 'Please enter messaging sender id', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter messaging sender id from your firebase app configuration. e.g: 460254094425', 'ultimate-push-notifications' ),
				),
				'cs_app_config[appId]'             => array(
					'title'       => __( 'APP ID', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'appId', $option, '' ),
					'placeholder' => __( 'Please enter app id', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter app id from your firebase app configuration. e.g: 1:460254094425:web:7a894f44e90d3e209042ac', 'ultimate-push-notifications' ),
				),
				'cs_app_config[measurementId]'     => array(
					'title'       => __( 'Measurement ID', 'ultimate-push-notifications' ),
					'type'        => 'text',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'measurementId', $option, '' ),
					'placeholder' => __( 'please enter measurement id', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter measurement id from your firebase app configuration. e.g: G-RH8F&MSE5R', 'ultimate-push-notifications' ),
				),
				'cs_app_config[key]'               => array(
					'title'       => __( 'Server Key', 'ultimate-push-notifications' ),
					'type'        => 'textarea',
					'class'       => 'form-control',
					'required'    => true,
					'value'       => FormBuilder::get_value( 'key', $option, '' ),
					'placeholder' => __( 'please enter server key', 'ultimate-push-notifications' ),
					'desc_tip'    => __( 'Enter server key from your firebase app configuration. e.g: AAAAayUMFs.. You\'ll be able to get it from firebase app: settings -> Cloud Messaging section.', 'ultimate-push-notifications' ),
				),
			);

			$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

			$swal_title           = __( 'Saving configuration', 'ultimate-push-notifications' );
			$btn_txt              = __( 'Save Config', 'ultimate-push-notifications' );
			$update_hidden_fields = array();
			if ( ! empty( $option ) ) {
				$swal_title = __( 'Updating configuration', 'ultimate-push-notifications' );
				$btn_txt    = __( 'Update config', 'ultimate-push-notifications' );

				$update_hidden_fields = array(
					'cs_app_config_update[id]' => array(
						'id'    => 'id',
						'type'  => 'hidden',
						'value' => 1,
					),
				);

			}

			$hidden_fields = array_merge_recursive(
				array(
					'method'     => array(
						'id'    => 'method',
						'type'  => 'hidden',
						'value' => "admin\\options\\functions\\AppConfig@save",
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
                        Please register on - <a href='https://console.firebase.google.com/' target=\"_blank\">https://console.firebase.google.com/</a> and create a project
                    </li>
                    <li>
                        After creating project on firebase, create a APP by clicking on 'Add app' button
                    </li>
                    <li>
                        When app platform appear, click the 'web' to create your app. Then follow the steps.
                    </li>
                    <li>
                        After registered your app, you will see the following configuration field's value. Get these and setup the following configuration.
                    </li>
                </ol>
            </li>
        </ul>";

			return $this->Admin_Page_Generator->generate_page( $args );
		}

	}

}
