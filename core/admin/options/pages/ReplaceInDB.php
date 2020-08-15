<?php namespace UltimatePushNotifications\admin\options\pages;

/**
 * Class: Replace in db
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\builders\FormBuilder;
use UltimatePushNotifications\admin\builders\AdminPageBuilder;

class ReplaceInDB {

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

		add_action( 'admin_footer', array( $this, 'default_page_scripts' ) );
	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_default_settings( $args ) {

		$settings = isset( $args['gateway_settings'] ) ? (object) $args['gateway_settings'] : '';
		$option   = isset( $settings->defaultOptn ) ? $settings->defaultOptn : '';

		$fields = array(
			'cs_db_string_replace[find]'    => array(
				'title'       => __( 'Find', 'ultimate-push-notifications' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'value'       => '',
				'placeholder' => __( 'Enter word to find ', 'ultimate-push-notifications' ),
				'desc_tip'    => __( 'Enter a word you want to find in Database. e.g: _test ', 'ultimate-push-notifications' ),
			),
			'cs_db_string_replace[replace]' => array(
				'title'       => __( 'Replace With', 'ultimate-push-notifications' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'value'       => '',
				'placeholder' => __( 'Enter word to replace with', 'ultimate-push-notifications' ),
				'desc_tip'    => __( 'Enter word you want to replace with. e.g : test', 'ultimate-push-notifications' ),
			),
		);

		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$hidden_fields = array(
			'method'     => array(
				'id'    => 'method',
				'type'  => 'hidden',
				'value' => "admin\\functions\\DbReplacer@db_string_replace",
			),
			'swal_title' => array(
				'id'    => 'swal_title',
				'type'  => 'hidden',
				'value' => 'Settings Updating',
			),

		);
		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']   = 'Find & Replace';
		$args['show_btn']   = true;
		$args['body_class'] = 'no-bottom-margin';
		$args['well']       = "<ul>
                        <li> <b>Warning!</b>
                            <ol>
                                <li>
                                    Replacement in database is permanent. You can't un-done it, once it get replaced.
                                </li>
                            </ol>
                        </li>
                    </ul>";

		return $this->Admin_Page_Generator->generate_page( $args );
	}

	/**
	 * Add custom scripts
	 */
	public function default_page_scripts() {
		
	}

}
