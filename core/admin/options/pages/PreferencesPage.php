<?php namespace UltimatePushNotifications\admin\options\pages;

use UltimatePushNotifications\automation\PreferencesForm;

/**
 * "Set Notifications": a member chooses which automations reach them.
 *
 * Replaces the per-user copy editor. Message text now lives on the rule;
 * the member decides receive-or-not.
 *
 * @package Options
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

class PreferencesPage {

	const PAGE = 'cs-upn-set-notifications';

	/**
	 * @return void
	 */
	public static function render() {
		$result = PreferencesForm::handle();
		?>
		<?php \UltimatePushNotifications\admin\builders\Layout::open( \__( 'Set Notifications', 'ultimate-push-notifications' ), \__( 'Which automations you want to hear about on your own devices.', 'ultimate-push-notifications' ), '', 'upn-preferences' ); ?>
			<div class="well"><p>
				<?php \esc_html_e( 'Automated notifications the site sends to people like you. Turn off any you do not want; the rest arrive on every device you have registered.', 'ultimate-push-notifications' ); ?>
			</p></div>

			<?php if ( true === $result ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php \esc_html_e( 'Saved.', 'ultimate-push-notifications' ); ?></p></div>
			<?php elseif ( \is_wp_error( $result ) ) : ?>
				<div class="notice notice-error"><p><?php echo \esc_html( $result->get_error_message() ); ?></p></div>
			<?php endif; ?>

			<?php PreferencesForm::render( \admin_url( 'admin.php?page=' . self::PAGE ) ); ?>

			<p class="description">
				<?php
				\printf(
					/* translators: %s: link */
					\esc_html__( 'No device registered yet? %s', 'ultimate-push-notifications' ),
					'<a href="' . \esc_url( \admin_url( 'admin.php?page=cs-upn-register-my-device' ) ) . '">' . \esc_html__( 'Register this browser.', 'ultimate-push-notifications' ) . '</a>'
				);
				?>
			</p>
		<?php \UltimatePushNotifications\admin\builders\Layout::close(); ?>
		<?php
	}

}
