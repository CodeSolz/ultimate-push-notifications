<?php namespace UltimatePushNotifications\admin\notices;

/**
 * Admin Notice
 *
 * @package Notices
 * @since 1.0.0
 * @author M.Tuhin <tuhin@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\builders\NoticeBuilder;

if ( ! \class_exists( 'UpnNotices' ) ) {

	class UpnNotices {

		/**
		 * Activated Notice
		 *
		 * @return String
		 */
		public static function activated() {
			$notice        = NoticeBuilder::get_instance();
			$message       = __( 'Thank you for choosing us. Let\'s %1$s set some find & replace rules. %2$s', 'ultimate-push-notifications' );
			$register_link = admin_url( 'admin.php?page=cs-add-replacement-rule' );
			$default_link  = site_url( '' );
			$message       = sprintf(
				$message,
				'<a href="' . $register_link . '"><strong>',
				'</strong></a>',
				'<a target="_blank" href="' . $default_link . '"><strong>',
				'</strong></a>'
			);
			$notice->info( $message, 'Activated' );
		}

		/**
		 * Feedback
		 *
		 * @return void
		 */
		public static function feeback() {
			$notice        = NoticeBuilder::get_instance();
			$message       = __( '', 'ultimate-push-notifications' );
			$register_link = admin_url( 'admin.php?page=cs-igt-test-url-slug-settings' );
			$default_link  = site_url( '' );
			$message       = sprintf(
				$message,
				'<a href="' . $register_link . '"><strong>',
				'</strong></a>',
				'<a target="_blank" href="' . $default_link . '"><strong>',
				'</strong></a>'
			);
			$notice->info( $message, 'Activated' );
		}


	}

}
