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

class Activate {

	/**
	 * Option holding the schema version currently applied to this site.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'upn_db_version';

	/**
	 * Install / upgrade DB
	 *
	 * @return void
	 */
	public static function on_activate() {
		self::install_tables();
		self::ensure_indexes();

		add_option( self::DB_VERSION_OPTION, CS_UPN_DB_VERSION );
		add_option( 'upn_plugin_version', CS_UPN_VERSION );
		add_option( 'upn_plugin_install_date', \current_time( 'mysql' ) );
	}

	/**
	 * Create or upgrade the plugin's tables.
	 *
	 * Uses dbDelta() rather than raw CREATE TABLE IF NOT EXISTS. The previous
	 * implementation could only ever create tables on a site that had none, so a
	 * column or table added in a later release was never delivered to an existing
	 * install. dbDelta() diffs the desired schema against the live one and issues
	 * the ALTERs, and it is safe to run repeatedly.
	 *
	 * @return void
	 */
	private static function install_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * dbDelta is whitespace-sensitive: one field per line, two spaces after
		 * PRIMARY KEY, and KEY definitions on their own lines. Column definitions
		 * are deliberately left as they were so dbDelta emits index changes only
		 * and does not attempt to retype populated columns.
		 */
		$schemas = array(
			/*
			 * A row is either a Web Push subscription (endpoint + p256dh + auth)
			 * or a legacy FCM device (token). Both shapes share this table so an
			 * upgrading site keeps delivering to its existing devices while new
			 * ones register over Web Push.
			 *
			 * endpoint is indexed on a prefix because push endpoints are long
			 * and only their leading path is discriminating.
			 */
			"CREATE TABLE {$wpdb->prefix}upn_user_devices (
			id int(11) NOT NULL auto_increment,
			user_id bigint DEFAULT NULL,
			transport varchar(20) NOT NULL DEFAULT 'fcm_legacy',
			endpoint text,
			p256dh varchar(255) DEFAULT NULL,
			auth_secret varchar(255) DEFAULT NULL,
			token mediumtext,
			device_id mediumtext,
			user_agent varchar(255) DEFAULT NULL,
			browser varchar(20) DEFAULT NULL,
			os varchar(20) DEFAULT NULL,
			device varchar(20) DEFAULT NULL,
			locale varchar(20) DEFAULT NULL,
			timezone varchar(64) DEFAULT NULL,
			consent_on datetime DEFAULT NULL,
			consent_source varchar(500) DEFAULT NULL,
			registered_on datetime DEFAULT NULL,
			last_seen_on datetime DEFAULT NULL,
			last_click_on datetime DEFAULT NULL,
			last_success_on datetime DEFAULT NULL,
			last_error varchar(191) DEFAULT NULL,
			total_sent_success_notifications bigint(20) DEFAULT 0,
			total_sent_fail_notifications bigint(20) DEFAULT 0,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY transport (transport),
			KEY browser (browser),
			KEY last_seen_on (last_seen_on),
			KEY last_click_on (last_click_on)
			) $charset_collate",

			/*
			 * Background jobs. job_key is UNIQUE — that is what makes enqueue
			 * idempotent under retry, and why completed jobs are deleted rather
			 * than marked done.
			 */
			"CREATE TABLE {$wpdb->prefix}upn_jobs (
			id bigint(20) unsigned NOT NULL auto_increment,
			job_key char(40) NOT NULL default '',
			job_type varchar(32) NOT NULL default '',
			payload text,
			status varchar(16) NOT NULL default 'pending',
			attempts tinyint(3) unsigned NOT NULL default 0,
			claim_token char(32) default NULL,
			claimed_at datetime default NULL,
			available_at datetime default NULL,
			last_error varchar(255) default NULL,
			created_at datetime default NULL,
			updated_at datetime default NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_key (job_key),
			KEY status_available (status,available_at),
			KEY claim_token (claim_token)
			) $charset_collate",

			"CREATE TABLE {$wpdb->prefix}upn_notification_log (
			id int(11) NOT NULL auto_increment,
			notification_type varchar(100) DEFAULT NULL,
			title text,
			body text,
			icon varchar(500) DEFAULT NULL,
			image varchar(500) DEFAULT NULL,
			click_action varchar(500) DEFAULT NULL,
			recipients int(11) DEFAULT 0,
			success_count int(11) DEFAULT 0,
			fail_count int(11) DEFAULT 0,
			pruned_count int(11) DEFAULT 0,
			retry_count int(11) DEFAULT 0,
			skipped_count int(11) DEFAULT 0,
			click_count int(11) DEFAULT 0,
			first_error varchar(191) DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY sent_at (sent_at),
			KEY notification_type (notification_type)
			) $charset_collate",
		);

		foreach ( $schemas as $schema ) {
			\dbDelta( $schema );
		}
	}

	/**
	 * Add indexes dbDelta cannot manage reliably.
	 *
	 * A prefix index on a TEXT column round-trips badly through dbDelta's index
	 * comparison, so it is applied here behind an explicit existence check. The
	 * token lookup is on the hot path for every send.
	 *
	 * @return void
	 */
	private static function ensure_indexes() {
		global $wpdb;

		$table = $wpdb->prefix . 'upn_user_devices';

		// Index name => column expression. Both are on the hot path: token for
		// legacy FCM lookups, endpoint for Web Push deduplication on re-subscribe.
		$indexes = array(
			'token'    => '`token`(64)',
			'endpoint' => '`endpoint`(191)',
		);

		foreach ( $indexes as $name => $expression ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
					$name
				)
			);

			if ( null === $exists ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$expression})" );
			}
		}
	}

	/**
	 * Check DB Status
	 *
	 * Runs on plugins_loaded. Installs on a fresh site, and upgrades when the
	 * schema version shipped with the plugin is newer than the one recorded for
	 * this site.
	 *
	 * @return void
	 */
	public static function check_db_status() {
		$installed_db_version = \get_option( self::DB_VERSION_OPTION );

		if ( empty( $installed_db_version ) ) {
			self::on_activate();
		} elseif ( \version_compare( $installed_db_version, CS_UPN_DB_VERSION, '<' ) || self::missing_tables() ) {
			/*
			 * The version comparison catches a normal upgrade. The table check
			 * catches everything else: a dbDelta that failed silently on a host
			 * that forbids CREATE TABLE, a table dropped by hand, or a schema
			 * that gained a table without the version being bumped. Without it,
			 * a site can be stuck at "current" with a table missing forever.
			 */
			self::install_tables();
			self::ensure_indexes();
			self::reset_table_cache();
			\update_option( self::DB_VERSION_OPTION, CS_UPN_DB_VERSION );
		}

		\update_option( 'upn_plugin_version', CS_UPN_VERSION );

		return true;
	}

	/**
	 * Every table the plugin expects, fully prefixed.
	 *
	 * @return string[]
	 */
	public static function expected_tables() {
		global $wpdb;

		return array(
			$wpdb->prefix . 'upn_user_devices',
			$wpdb->prefix . 'upn_jobs',
			$wpdb->prefix . 'upn_notification_log',
		);
	}

	/**
	 * Memoised result of missing_tables() for this request.
	 *
	 * @var string[]|null
	 */
	private static $missing_memo = null;

	/**
	 * Which expected tables are absent?
	 *
	 * Cheap enough to run on plugins_loaded: one SHOW TABLES per table, and
	 * the result is memoised for the request so the queue and health check can
	 * ask again for free.
	 *
	 * @return string[] Missing table names, empty when all present.
	 */
	public static function missing_tables() {
		if ( null !== self::$missing_memo ) {
			return self::$missing_memo;
		}

		global $wpdb;

		$missing = array();

		foreach ( self::expected_tables() as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found !== $table ) {
				$missing[] = $table;
			}
		}

		self::$missing_memo = $missing;

		return $missing;
	}

	/**
	 * Forget the memoised table check, after an install attempt.
	 *
	 * @return void
	 */
	public static function reset_table_cache() {
		self::$missing_memo = null;
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
