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
use UltimatePushNotifications\admin\builders\Screens;
use UltimatePushNotifications\admin\options\functions\AppConfig;
use UltimatePushNotifications\admin\functions\Compose;
use UltimatePushNotifications\admin\options\pages\ComposePage;
use UltimatePushNotifications\admin\options\pages\AutomationsPage;
use UltimatePushNotifications\admin\options\pages\OptInPage;
use UltimatePushNotifications\admin\options\pages\PreferencesPage;
use UltimatePushNotifications\health\HealthPage;

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
			'read',
			CS_UPN_PLUGIN_IDENTIFIER,
			array( $this, 'upn_page_landing' ),
			CS_UPN_PLUGIN_ASSET_URI . 'img/icon-24x24.png',
			57
		);

		/*
		 * Screens register with a group; Screens::emit() turns each group into one
		 * menu entry with tabs (the Pro plugin adds its screens to the same groups).
		 * Every slug stays routable on its own.
		 */
		Screens::boot();
		Screens::register( 'cs-upn-compose', __( 'Compose', 'ultimate-push-notifications' ), Compose::capability(), array( $this, 'upn_page_compose' ), 'compose', __( 'Compose a Notification', 'ultimate-push-notifications' ), 10 );
		Screens::register( 'cs-upn-automations', __( 'Automations', 'ultimate-push-notifications' ), \UltimatePushNotifications\admin\options\pages\RulesPage::capability(), array( $this, 'upn_page_automations' ), 'automations', '', 10 );
		Screens::register( 'cs-upn-all-registered-devices', __( 'All Registered Devices', 'ultimate-push-notifications' ), 'administrator', array( $this, 'upn_page_all_registered_devices' ), 'subscribers', __( 'All Registered devices', 'ultimate-push-notifications' ), 10 );
		Screens::register( 'cs-upn-register-my-device', __( 'Register My Device', 'ultimate-push-notifications' ), 'read', array( $this, 'upn_page_register_my_device' ), 'subscribers', __( 'Register my device', 'ultimate-push-notifications' ), 20 );
		Screens::register( 'cs-upn-set-notifications', __( 'Set Notifications', 'ultimate-push-notifications' ), 'read', array( $this, 'upn_set_notifications' ), 'subscribers', __( 'Set notifications', 'ultimate-push-notifications' ), 30 );
		Screens::register( 'cs-upn-optin', __( 'Subscribe Prompt', 'ultimate-push-notifications' ), 'manage_options', array( $this, 'upn_page_optin' ), 'optin', '', 10 );
		Screens::register( 'cs-upn-health', __( 'Health', 'ultimate-push-notifications' ), 'manage_options', array( $this, 'upn_page_health' ), 'health', __( 'Push Notification Health', 'ultimate-push-notifications' ), 10 );
		Screens::register( 'cs-upn-app-configuration', __( 'App Config', 'ultimate-push-notifications' ), 'administrator', array( $this, 'upn_app_config' ), 'settings', __( 'APP Configuration', 'ultimate-push-notifications' ), 10 );

		// The Pro screens as tabs with a badge until Pro registers them itself.
		\UltimatePushNotifications\pro\Adverts::register();

		// The hook suffixes the footer scripts are keyed by, once the menu is built.
		add_action( 'admin_menu', array( $this, 'upn_collect_menu_hooks' ), 51 );

		// load script: the plugin's look on every one of its screens, the Pro plugin's included.
		add_action( 'current_screen', array( $this, 'upn_maybe_register_admin_settings_scripts' ) );

		remove_submenu_page( CS_UPN_PLUGIN_IDENTIFIER, CS_UPN_PLUGIN_IDENTIFIER );

		// init pages
		$this->pages = new AdminPageBuilder();
		$upn_menu    = $this->upn_menus;
	}

	/**
	 * The top-level entry itself, if it is ever opened directly: the first screen the user may see.
	 *
	 * @return void
	 */
	public function upn_page_landing() {
		foreach ( Screens::all() as $p ) {
			if ( current_user_can( $p['cap'] ) ) {
				call_user_func( $p['callback'] );
				return;
			}
		}
		wp_die( esc_html__( 'You do not have permission to view this page.', 'ultimate-push-notifications' ) );
	}

	/**
	 * Hook suffixes by the old keys, after Screens built the menu.
	 *
	 * @return void
	 */
	public function upn_collect_menu_hooks() {
		foreach ( array( 'menu_app_config' => 'cs-upn-app-configuration', 'menu_set_notifications' => 'cs-upn-set-notifications', 'menu_add_my_device' => 'cs-upn-register-my-device', 'menu_all_registered_devices' => 'cs-upn-all-registered-devices', 'menu_compose' => 'cs-upn-compose', 'menu_optin' => 'cs-upn-optin', 'menu_automations' => 'cs-upn-automations', 'menu_health' => 'cs-upn-health' ) as $key => $slug ) {
			$this->upn_menus[ $key ] = Screens::hook( $slug );
		}
		$GLOBALS['upn_menu'] = $this->upn_menus;
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
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ultimate-push-notifications' ) );
		}

		PreferencesPage::render();
	}

	/**
	 * Register my device
	 *
	 * @return void
	 */
	public function upn_page_register_my_device() {
		$option    = array();
		$option = $this->upn_has_config( $option );
		$page_info = array(
			'title'     => __( 'My Registered Devices', 'ultimate-push-notifications' ),
			'sub_title' => __( 'By visiting this page your device will be automatically registered. Please read the hints to understand more.', 'ultimate-push-notifications' ),
		);

		if ( current_user_can( 'read' ) || current_user_can( 'read' ) ) {
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
		$option    = array();
		$option = $this->upn_has_config( $option );
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
	public function upn_maybe_register_admin_settings_scripts( $screen ) {
		$id = \is_object( $screen ) && isset( $screen->id ) ? (string) $screen->id : '';
		if ( '' === $id ) {
			return;
		}
		if ( \in_array( $id, (array) $this->upn_menus, true ) || false !== \strpos( $id, 'cs-upn' ) || false !== \strpos( $id, CS_UPN_PLUGIN_IDENTIFIER ) ) {
			$this->upn_register_admin_settings_scripts();
		}
	}

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

	/**
	 * has config setup
	 *
	 * @param array $option
	 * @return void
	 */
	private function upn_has_config( $option = array() ){
		$app_config    = AppConfig::get_config();
		$hasConfig = empty( $app_config ) ? false : true;
		$option = array_merge( (array) $option, array(
			'hasConfigSetup' => $hasConfig
		));

		return $option;
	}

	/**
	 * Compose page
	 *
	 * @return void
	 */
	public function upn_page_compose() {
		if ( ! current_user_can( Compose::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ultimate-push-notifications' ) );
		}

		ComposePage::render();
	}

	/**
	 * Subscribe Prompt page
	 *
	 * @return void
	 */
	public function upn_page_optin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ultimate-push-notifications' ) );
		}

		OptInPage::render();
	}

	/**
	 * Automations page
	 *
	 * @return void
	 */
	public function upn_page_automations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ultimate-push-notifications' ) );
		}

		AutomationsPage::render();
	}

	/**
	 * Health page
	 *
	 * @return void
	 */
	public function upn_page_health() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ultimate-push-notifications' ) );
		}

		HealthPage::render();
	}

}
