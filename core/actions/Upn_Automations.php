<?php namespace UltimatePushNotifications\actions;

/**
 * Boots the automations: push on publish, and the event rule engine.
 *
 * @package Action
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\admin\options\pages\AutomationsPage;
use UltimatePushNotifications\admin\options\pages\RulesPage;
use UltimatePushNotifications\automation\AutoPush;
use UltimatePushNotifications\automation\Engine;
use UltimatePushNotifications\automation\Preferences;

class Upn_Automations {

	function __construct() {
		AutoPush::boot();
		Engine::boot();

		add_action( 'admin_init', array( AutomationsPage::class, 'register' ) );
		add_action( 'admin_init', array( RulesPage::class, 'handle_request' ) );

		// A member's mutes go with their account.
		add_action( 'deleted_user', array( Preferences::class, 'forget' ) );
	}

}
