<?php namespace UltimatePushNotifications\admin\builders;

/**
 * Custom Notice
 *
 * @package Notices
 * @since 1.0.0
 * @author M.Tuhin <tuhin@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}


class NoticeBuilder {

	private static $_instance;
	private $admin_notices;
	const TYPES = 'error,warning,info,success';

	private function __construct() {
		$this->admin_notices = new \stdClass();
		foreach ( explode( ',', self::TYPES ) as $type ) {
			$this->admin_notices->{$type} = array();
		}

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
	}

	/**
	 * generate instance
	 *
	 * @return void
	 */
	public static function get_instance() {
		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Admin init
	 *
	 * @return void
	 */
	public function action_admin_init() {
		$dismiss_option = filter_input( INPUT_GET, CS_UPN_ACTIVATE_NOTICE_ID, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		echo $dismiss_option;
		if ( is_string( $dismiss_option ) ) {
			update_option( CS_UPN_ACTIVATE_NOTICE_ID . 'ed_' . $dismiss_option, true );
			wp_die();
		}
	}

	/**
	 * Init notices
	 *
	 * @return void
	 */
	public function action_admin_notices() {

		global $my_admin_page, $upn_menus;
		$screen = get_current_screen();

		// pre_print( $screen );

		if ( false !== \stripos( $screen->id, 'upush-notifier_page_cs-upn' ) ) {
			return;
		}

		foreach ( explode( ',', self::TYPES ) as $type ) {
			foreach ( $this->admin_notices->{$type} as $admin_notice ) {

				$dismiss_url = add_query_arg(
					array(
						CS_UPN_ACTIVATE_NOTICE_ID => $admin_notice->dismiss_option,
					),
					admin_url()
				);

				if ( ! get_option( CS_UPN_ACTIVATE_NOTICE_ID . "ed_{$admin_notice->dismiss_option}" ) ) {

					?><div class="notice cs-notice notice-
					<?php
					echo $type;

					if ( $admin_notice->dismiss_option ) {
						echo ' is-dismissible" data-dismiss-url="' . esc_url( $dismiss_url );
					}
					?>
					">
					<p>
						<strong><?php echo CS_UPN_PLUGIN_NAME; ?></strong>
					</p>
					<p><?php echo $admin_notice->message; ?></p>

				</div>
						<?php
				}
			}
		}
	}

	public function error( $message, $dismiss_option = false ) {
		$this->notice( 'error', $message, $dismiss_option );
	}

	public function warning( $message, $dismiss_option = false ) {
		$this->notice( 'warning', $message, $dismiss_option );
	}

	public function success( $message, $dismiss_option = false ) {
		$this->notice( 'success', $message, $dismiss_option );
	}

	public function info( $message, $dismiss_option = false ) {
		$this->notice( 'info', $message, $dismiss_option );
	}

	private function notice( $type, $message, $dismiss_option ) {
		$notice                 = new \stdClass();
		$notice->message        = $message;
		$notice->dismiss_option = $dismiss_option;

		$this->admin_notices->{$type}[] = $notice;
	}

}

