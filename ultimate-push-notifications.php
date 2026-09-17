<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Ultimate Push Notifications
 * Plugin URI:        https://codesolz.net/our-products/wordpress-plugin/ultimate-push-notifications/
 * Description:       Self-hosted web push for WordPress — one-click setup, unlimited subscribers in your own database, a composer with live preview, push on publish, 29 automations for WooCommerce, BuddyPress, Contact Form 7 and membership plugins, a background delivery queue, click tracking and a health check that tells you when the channel breaks. Works on desktop, Android and iPhone. No Firebase, no per-subscriber fees.
 * Version:           1.6.2
 * Author:            CodeSolz
 * Author URI:        https://www.codesolz.net
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl.txt
 * Domain Path:       /languages
 * Text Domain:       ultimate-push-notifications
 * Requires PHP: 7.4
 * Requires At Least: 4.0
 * Tested Up To: 7.0
 * WC requires at least: 4.0
 * WC tested up to: 11.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ultimate_Push_Notifications {

	/**
	 * Hold actions hooks
	 *
	 * @var array
	 */
	private static $upn_hooks = array();

	/**
	 * Hold version
	 *
	 * @var String
	 */
	private static $version = '1.6.2';

	/**
	 * Hold version
	 *
	 * @var String
	 */
	private static $db_version = '1.6.1';

	/**
	 * Hold nameSpace
	 *
	 * @var string
	 */
	private static $namespace = 'UltimatePushNotifications';


	public function __construct() {

		// load plugins constant
		self::set_constants();

		// load core files
		self::load_core_framework();

		// load init
		self::load_hooks();

		/** Called during the plugin activation */
		self::on_activate();

		/**load textdomain */
		add_action( 'plugins_loaded', array( __CLASS__, 'init_textdomain' ), 15 );

		/**Init necessary functions */
		add_action( 'plugins_loaded', array( __CLASS__, 'upn_init_function' ), 17 );

		/**check plugin db*/
		add_action( 'plugins_loaded', array( __CLASS__, 'upn_check_db' ), 17 );
	}

	/**
	 * Set constant data
	 */
	private static function set_constants() {

		$constants = array(
			'CS_UPN_VERSION'            => self::$version, // Define current version
			'CS_UPN_DB_VERSION'         => self::$db_version, // Define current db version
			'CS_UPN_HOOKS_DIR'          => untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/core/actions/', // plugin hooks dir
			'CS_UPN_BASE_DIR_PATH'      => untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/', // Hold plugins base dir path
			'CS_UPN_PLUGIN_ASSET_URI'   => plugin_dir_url( __FILE__ ) . 'assets/', // Define asset uri
			'CS_UPN_PLUGIN_LIB_URI'     => plugin_dir_url( __FILE__ ) . 'lib/', // Library uri
			'CS_UPN_PLUGIN_IDENTIFIER'  => plugin_basename( __FILE__ ), // plugins identifier - base dir
			'CS_UPN_PLUGIN_NAME'        => 'Ultimate Push Notifications', // Plugin name
			'CS_UPN_ACTIVATE_NOTICE_ID' => 'upn_notice_dismiss', // Plugin Notice id
		);

		foreach ( $constants as $name => $value ) {
			self::set_constant( $name, $value );
		}

		return true;
	}

	/**
	 * Set constant
	 *
	 * @param type $name
	 * @param type $value
	 * @return boolean
	 */
	private static function set_constant( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
		return true;
	}


	/**
	 * load core framework
	 */
	private static function load_core_framework() {
			require_once CS_UPN_BASE_DIR_PATH . 'vendor/autoload.php';
	}

	/**
	 * Load Action Files
	 *
	 * @return classes
	 */
	private static function load_hooks() {
		$namespace = self::$namespace . '\\actions\\';
		foreach ( glob( CS_UPN_HOOKS_DIR . '*.php' ) as $cs_action_file ) {
			$class_name = basename( $cs_action_file, '.php' );
			$class      = $namespace . $class_name;
			if ( class_exists( $class ) &&
				! array_key_exists( $class, self::$upn_hooks ) ) { // check class doesn't load multiple time
				self::$upn_hooks[ $class ] = new $class();
			}
		}
		return self::$upn_hooks;
	}

	/**
	 * init activation hook
	 */
	private static function on_activate() {

		// activation hook
		register_activation_hook( __FILE__, array( self::$namespace . '\\install\\Activate', 'on_activate' ) );

		// deactivation hook
		register_deactivation_hook( __FILE__, array( self::$namespace . '\\install\\Activate', 'on_deactivate' ) );

		return true;
	}

	/**
	 * init textdomain
	 */
	public static function init_textdomain() {
		load_plugin_textdomain( 'ultimate-push-notifications', false, CS_UPN_BASE_DIR_PATH . '/languages/' );
	}

	/**
	 * Init plugin's functions
	 *
	 * @return void
	 */
	public static function upn_init_function() {
		// show activation notice
		\UltimatePushNotifications\admin\notices\UpnNotices::activated();
	}

	/**
	 * Check DB
	 *
	 * @return void
	 */
	public static function upn_check_db() {
		$cls_install = self::$namespace . '\install\Activate';
		$cls_install::check_db_status();
	}

}

/*
 * WooCommerce: orders are only ever read through wc_get_order() / order objects
 * and the analytics lookup tables, so both order storages work. Say so, or
 * WooCommerce lists the plugin as incompatible with HPOS.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

global $UPN;
$UPN = new Ultimate_Push_Notifications();
