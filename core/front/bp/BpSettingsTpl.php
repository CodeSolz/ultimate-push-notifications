<?php namespace UltimatePushNotifications\front\bp;

/**
 * The "Push Notifications" tab under a member's BuddyPress notification
 * settings: the same preference form as wp-admin, in the member's own
 * profile.
 *
 * @package Front
 * @since 1.0.0
 * @since 1.6.0 Renders the automation preference form; the per-user copy
 *              editor is gone.
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\automation\PreferencesForm;

class BpSettingsTpl {

	/** @var true|\WP_Error|null */
	private $result = null;

	public function __construct() {
		// Save before anything is rendered so the form shows the new state.
		$this->result = $this->can_edit() ? PreferencesForm::handle() : null;

		add_action( 'bp_template_title', array( $this, 'title' ) );
		add_action( 'bp_template_content', array( $this, 'content' ) );
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * A member may only edit their own tab, never one they are merely viewing.
	 *
	 * @return bool
	 */
	private function can_edit() {
		return \is_user_logged_in()
			&& \function_exists( 'bp_displayed_user_id' )
			&& (int) \bp_displayed_user_id() === \get_current_user_id();
	}

	/**
	 * @return void
	 */
	public function title() {
		\esc_html_e( 'Push Notifications', 'ultimate-push-notifications' );
	}

	/**
	 * @return void
	 */
	public function content() {
		if ( ! $this->can_edit() ) {
			$this->notice( \__( 'Only the account owner can change these settings.', 'ultimate-push-notifications' ), 'error' );
			return;
		}

		if ( true === $this->result ) {
			$this->notice( \__( 'Saved.', 'ultimate-push-notifications' ), 'success' );
		} elseif ( \is_wp_error( $this->result ) ) {
			$this->notice( $this->result->get_error_message(), 'error' );
		}

		$action = \function_exists( 'bp_displayed_user_domain' )
			? \trailingslashit( \bp_displayed_user_domain() ) . 'notifications/push-notifications/'
			: '';

		PreferencesForm::render( $action, 'standard-form base notifications-settings-form' );
	}

	/**
	 * A BuddyPress-styled feedback notice.
	 *
	 * @param string $message
	 * @param string $type success|error
	 * @return void
	 */
	private function notice( $message, $type = 'success' ) {
		$class = ( 'error' === $type ) ? 'error upn-bp-error' : 'success upn-bp-success';
		?>
		<aside class="bp-feedback bp-messages bp-template-notice <?php echo \esc_attr( $class ); ?>">
			<span class="bp-icon" aria-hidden="true"></span>
			<p><?php echo \esc_html( \wp_strip_all_tags( (string) $message ) ); ?></p>
		</aside>
		<?php
	}

}
