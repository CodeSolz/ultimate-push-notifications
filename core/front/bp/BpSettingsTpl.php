<?php namespace UltimatePushNotifications\front\bp;

/**
 * Class: Admin Menu Scripts
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\admin\builders\FormBuilder;
use UltimatePushNotifications\admin\options\pages\SetNotifications;
use UltimatePushNotifications\admin\options\functions\SetNotifications as FuncSetNotifications;

class BpSettingsTpl {

	/**
	 * Form Generator
	 *
	 * @var type
	 */
	private $Form_Generator;

	public function __construct() {
		// global $bp;

		add_action( 'bp_template_title', array( $this, 'upn_bp_settings_top_points_events_title' ) );
		add_action( 'bp_template_content', array( $this, 'upn_bp_settings_top_points_events_content' ) );
		\bp_core_load_template( \apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();
	}

	/**
	 * Section Title
	 *
	 * @return void
	 */
	public function upn_bp_settings_top_points_events_title() {
		_e( 'Settings', 'ultimate-push-notifications' );
	}

	/**
	 * Generate front-end BuddyPress settings for user
	 *
	 * @return void
	 */
	public function upn_bp_settings_top_points_events_content() {

		$options = FuncSetNotifications::get_notification_type();

		$buddyPress        = SetNotifications::upn_buddypress_fields( $options );
		$buddyPress['st1'] = array(
			'type'     => 'section_title',
			'title'    => __( 'Push Notifications', 'ultimate-push-notifications' ),
			'desc_tip' => __( 'Please set the following configuration to get push notifications when your browser is open.', 'ultimate-push-notifications' ),
		);

		?>
		<form action="<?php echo trailingslashit( bp_displayed_user_domain() ) . 'notifications/push-notifications'; ?>" method="post" id="" class="standard-form base notifications-settings-form">
			<?php do_action( 'upn_bp_front_notifications_settings' ); ?>	
			<?php echo $this->Form_Generator->generate_html_fields( $buddyPress ); ?>	
			<div class="submit">
				<?php wp_nonce_field( SECURE_AUTH_SALT, 'cs_token' ); ?>
				<input type="hidden" name="cs_set_notifications[single_settings]" value="1" />
				<input type="submit" name="profile-group-edit-submit" id="profile-group-edit-submit" value="<?php esc_attr_e( 'Save Changes', 'buddypress' ); ?> " />
			</div>
		</form>
		<?php
	}

}

