<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Register custom menu
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\admin\options\Scripts_Settings;
use UltimatePushNotifications\admin\builders\AdminPageBuilder;
use UltimatePushNotifications\admin\options\functions\AppConfig;
use UltimatePushNotifications\admin\options\functions\SetNotifications;

class Upn_RegisterMenu {

	/**
	 * Hold pages
	 *
	 * @var type
	 */
	private $pages;

	/**
	 *
	 * @var type
	 */
	private $WcFunc;

	/**
	 *
	 * @var type
	 */
	public $current_screen;

	/**
	 * Hold Menus
	 *
	 * @var [type]
	 */
	public $upn_menus;

	public function __construct() {
			// call WordPress admin menu hook
		add_action( 'admin_menu', array( $this, 'upn_register_menu' ) );
	}

	/**
	 * Init current screen
	 *
	 * @return type
	 */
	public function init_current_screen() {
			$this->current_screen = get_current_screen();
		return $this->current_screen;
	}

	/**
	 * Create plugins menu
	 */
	public function upn_register_menu() {
		global $upn_menu;
		add_menu_page(
			__( 'Ultimate Push Notifications', 'ultimate-push-notifications' ),
			'UPush Notifier',
			'manage_options',
			CS_UPN_PLUGIN_IDENTIFIER,
			'cs-woo-altcoin-gateway',
			CS_UPN_PLUGIN_ASSET_URI . 'img/icon-24x24.png',
			57
		);

		$this->upn_menus['menu_app_config'] = add_submenu_page(
			CS_UPN_PLUGIN_IDENTIFIER,
			__( 'APP Configuration', 'ultimate-push-notifications' ),
			'App Config',
			'administrator',
			'cs-upn-app-configuration',
			array( $this, 'upn_app_config' )
		);

		$this->upn_menus['menu_set_notifications'] = add_submenu_page(
			CS_UPN_PLUGIN_IDENTIFIER,
			__( 'Set notifications', 'ultimate-push-notifications' ),
			'Set Notifications',
			'manage_options',
			'cs-upn-set-notifications',
			array( $this, 'upn_set_notifications' )
		);

		$this->upn_menus['menu_add_my_device'] = add_submenu_page(
			CS_UPN_PLUGIN_IDENTIFIER,
			__( 'Register my device', 'ultimate-push-notifications' ),
			'Register My Device',
			'manage_options',
			'cs-upn-register-my-device',
			array( $this, 'upn_page_register_my_device' )
		);

		$this->upn_menus['menu_all_registered_devices'] = add_submenu_page(
			CS_UPN_PLUGIN_IDENTIFIER,
			__( 'All Registered devices', 'ultimate-push-notifications' ),
			'All Registered Devices',
			'administrator',
			'cs-upn-all-registered-devices',
			array( $this, 'upn_page_all_registered_devices' )
		);

		// load script
		add_action( "load-{$this->upn_menus['menu_app_config']}", array( $this, 'upn_register_admin_settings_scripts' ) );
		add_action( "load-{$this->upn_menus['menu_set_notifications']}", array( $this, 'upn_register_admin_settings_scripts' ) );
		add_action( "load-{$this->upn_menus['menu_add_my_device']}", array( $this, 'upn_register_admin_settings_scripts' ) );
		add_action( "load-{$this->upn_menus['menu_all_registered_devices']}", array( $this, 'upn_register_admin_settings_scripts' ) );

		remove_submenu_page( CS_UPN_PLUGIN_IDENTIFIER, CS_UPN_PLUGIN_IDENTIFIER );

		// init pages
		$this->pages = new AdminPageBuilder();
		$upn_menu    = $this->upn_menus;
	}

	/**
	 * Page App Configuration
	 *
	 * @return void
	 */
	public function upn_app_config() {
		$option    = AppConfig::get_config();
		$page_info = array(
			'title'     => sprintf( __( 'APP Configuration %1$s visible to administrator only %2$s', 'ultimate-push-notifications' ), '<span class="visibility" >(', ')</span>' ),
			'sub_title' => __( 'Please set the following application configuration correctly. You\'ll be able to find the following configuration data in your firebase application.', 'ultimate-push-notifications' ),
		);

		if ( current_user_can( 'administrator' ) ) {
			$AddNewRule = $this->pages->AppConfig();
			echo $this->generate_page( $AddNewRule, $page_info, $option );
		} else {
			echo $this->page_permission_restricted( $page_info );
		}

		return;
	}


	/**
	 * Page Set notifications
	 *
	 * @return void
	 */
	public function upn_set_notifications() {
		$option    = SetNotifications::get_notification_type();
		$page_info = array(
			'title'     => __( 'Set Notification', 'ultimate-push-notifications' ),
			'sub_title' => __( 'Please set the following settings to get notifications', 'ultimate-push-notifications' ),
		);

		if ( current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) ) {
			$SetNotifications = $this->pages->SetNotifications();
			echo $this->generate_page( $SetNotifications, $page_info, $option );
		} else {
			echo $this->page_permission_restricted( $page_info );
		}

		return;
	}

	/**
	 * Register my device
	 *
	 * @return void
	 */
	public function upn_page_register_my_device() {
		$option    = '';
		$page_info = array(
			'title'     => __( 'My Registered Devices', 'ultimate-push-notifications' ),
			'sub_title' => __( 'By visiting this page your device will be automatically registered. Please read the hints to understand more.', 'ultimate-push-notifications' ),
		);

		if ( current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) ) {
			$RegisterMyDevice = $this->pages->RegisterMyDevice();
			echo $this->generate_page( $RegisterMyDevice, $page_info, $option );
		} else {
			echo $this->page_permission_restricted( $page_info );
		}

		return;
	}

	/**
	 * Register my device
	 *
	 * @return void
	 */
	public function upn_page_all_registered_devices() {
		$option    = '';
		$page_info = array(
			'title'     => sprintf( __( 'All Registered Devices %1$s visible to administrator only %2$s', 'ultimate-push-notifications' ), '<span class="visibility" >(', ')</span>' ),
			'sub_title' => __( 'List of all the registered devices in the website', 'ultimate-push-notifications' ),
		);

		if ( current_user_can( 'administrator' ) ) {
			$AllRegisteredDevices = $this->pages->AllRegisteredDevices();
			echo $this->generate_page( $AllRegisteredDevices, $page_info, $option );
		} else {
			echo $this->page_permission_restricted( $page_info );
		}

		return;
	}

	/**
	 * Generate page
	 *
	 * @param [type] $Page_Obj
	 * @param [type] $page_info
	 * @param [type] $option
	 * @return void
	 */
	private function generate_page( $Page_Obj, $page_info, $option ) {
		if ( is_object( $Page_Obj ) ) {
			return $Page_Obj->generate_page( array_merge_recursive( $page_info, array( 'upn_custom_data' => array() ) ), $option );
		} else {
			return $Page_Obj;
		}
	}

	/**
	 * Page is restricted
	 *
	 * @param [type] $page_info
	 * @return void
	 */
	private function page_permission_restricted( $page_info ) {
		$AccessDenied = $this->pages->AccessDenied();
		if ( is_object( $AccessDenied ) ) {
			return $AccessDenied->generate_access_denided( array_merge_recursive( $page_info, array( 'upn_custom_data' => array() ) ) );
		} else {
			return $AccessDenied;
		}
	}

	/**
	 * load funnel builder scripts
	 */
	public function upn_register_admin_settings_scripts() {
		// register scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'upn_load_settings_scripts' ) );

		// init current screen
		$this->init_current_screen();

		// load all admin footer script
		add_action( 'admin_footer', array( $this, 'upn_load_admin_footer_script' ) );
	}

	/**
	 * Load admin scripts
	 */
	public function upn_load_settings_scripts( $page_id ) {
		return Scripts_Settings::load_admin_settings_scripts( $page_id, $this->upn_menus );

	}

	/**
	 * load custom scripts on admin footer
	 */
	public function upn_load_admin_footer_script() {
		return Scripts_Settings::load_admin_footer_script( $this->current_screen->id, $this->upn_menus );
	}


}
