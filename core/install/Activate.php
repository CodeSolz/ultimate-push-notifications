<?php namespace UltimatePushNotifications\install;

/**
 * Installation
 *
 * @package Install
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.com>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\admin\functions\Masking;

if ( ! \class_exists( 'Activate' ) ) {

	class Activate {


		/**
		 * Install DB
		 *
		 * @return void
		 */
		public static function on_activate() {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();

			$sqls = array(
				"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}upn_user_devices`(
				`id` int(11) NOT NULL auto_increment,
				`user_id` bigint,
				`token` mediumtext,
				`device_id` mediumtext,
				`registered_on` datetime,
				`total_sent_success_notifications` bigint(20),
				`total_sent_fail_notifications` bigint(20),
				PRIMARY KEY ( `id`)
				) $charset_collate",
				"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}upn_notifications`(
				`id` int(11) NOT NULL auto_increment,
				`user_id` bigint,
				`notification_type` text,
				PRIMARY KEY ( `id`)
				) $charset_collate",
			);

			foreach ( $sqls as $sql ) {
				if ( $wpdb->query( $sql ) === false ) {
					continue;
				}
			}

			// add db version to db
			add_option( 'upn_db_version', CS_UPN_DB_VERSION );
			add_option( 'upn_plugin_version', CS_UPN_VERSION );
			add_option( 'upn_plugin_install_date', date( 'Y-m-d H:i:s' ) );
		}

		/**
		 * Check DB Status
		 *
		 * @return void
		 */
		public static function check_db_status() {
			$import_old_settings          = false;
			$get_installed_db_version     = get_site_option( 'upn_db_version' );
			$get_installed_plugin_version = get_site_option( 'upn_plugin_version' );
			if ( empty( $get_installed_db_version ) ) {
				self::on_activate();
				$import_old_settings = true;
			}
			update_option( 'upn_plugin_version', CS_UPN_VERSION );
			update_option( 'upn_db_version', CS_UPN_DB_VERSION );

			return true;
		}

		/**
		 * Remove custom urls on detactive
		 *
		 * @return void
		 */
		public static function on_deactivate() {
			// remove notice status
			delete_option( CS_UPN_ACTIVATE_NOTICE_ID . 'ed_Activated' );
			return true;
		}


	}

}
