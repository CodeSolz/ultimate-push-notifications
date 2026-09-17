<?php namespace UltimatePushNotifications\automation;

/**
 * The form a member uses to turn automations on or off for themselves.
 *
 * One renderer, two homes: the admin "Set Notifications" screen and the
 * BuddyPress profile tab. The save path is the same in both and never
 * touches anyone else's preferences — a member can only edit their own.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class PreferencesForm {

	const NONCE = 'upn_prefs';
	const FIELD = 'upn_prefs';

	/**
	 * Save a submission for the current user.
	 *
	 * @return true|\WP_Error|null null when this request is not a submission.
	 */
	public static function handle() {
		if ( empty( $_POST[ self::FIELD . '_submit' ] ) ) {
			return null;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! \wp_verify_nonce( $nonce, self::NONCE ) ) {
			return new \WP_Error( 'upn_prefs_nonce', \__( 'Security check failed. Please reload the page and try again.', 'ultimate-push-notifications' ) );
		}

		$user_id = \get_current_user_id();
		if ( $user_id <= 0 ) {
			return new \WP_Error( 'upn_prefs_login', \__( 'You need to be logged in to change these settings.', 'ultimate-push-notifications' ) );
		}

		$wanted = isset( $_POST[ self::FIELD ] ) && \is_array( $_POST[ self::FIELD ] ) ? \array_map( 'intval', \array_keys( $_POST[ self::FIELD ] ) ) : array();

		// Only rules that apply to this member are listed, so only those can be changed.
		foreach ( \array_keys( Preferences::rules_for_user( $user_id ) ) as $rule_id ) {
			Preferences::set_muted( $user_id, $rule_id, ! \in_array( (int) $rule_id, $wanted, true ) );
		}

		return true;
	}

	/**
	 * @param string $action  Form action URL.
	 * @param string $classes Extra form classes (BuddyPress wants its own).
	 * @return void
	 */
	public static function render( $action, $classes = '' ) {
		$user_id = \get_current_user_id();
		$rules   = $user_id > 0 ? Preferences::rules_for_user( $user_id ) : array();
		?>
		<form method="post" action="<?php echo \esc_url( $action ); ?>" class="upn-prefs <?php echo \esc_attr( $classes ); ?>">
			<?php \wp_nonce_field( self::NONCE ); ?>

			<?php if ( ! $rules ) : ?>
				<p><?php \esc_html_e( 'There are no automated notifications that apply to you right now.', 'ultimate-push-notifications' ); ?></p>
			<?php else : ?>
				<p><?php \esc_html_e( 'Tick the notifications you want to receive on this account\'s registered devices.', 'ultimate-push-notifications' ); ?></p>
				<ul class="upn-prefs__list" style="list-style:none;margin:0 0 1em;padding:0">
					<?php foreach ( $rules as $id => $rule ) : ?>
						<?php $trigger = TriggerRegistry::get( $rule['trigger'] ); ?>
						<li style="margin:0 0 .6em">
							<label>
								<input type="checkbox" name="<?php echo \esc_attr( self::FIELD ); ?>[<?php echo (int) $id; ?>]" value="1" <?php \checked( ! Preferences::is_muted( $user_id, $id ) ); ?> />
								<strong><?php echo \esc_html( $rule['name'] ); ?></strong>
								<?php if ( $trigger ) : ?>
									<span class="description" style="opacity:.75"> — <?php echo \esc_html( $trigger->label() ); ?></span>
								<?php endif; ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><button type="submit" name="<?php echo \esc_attr( self::FIELD ); ?>_submit" value="1" class="button button-primary"><?php \esc_html_e( 'Save', 'ultimate-push-notifications' ); ?></button></p>
			<?php endif; ?>
		</form>
		<?php
	}

}
