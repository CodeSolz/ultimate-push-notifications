<?php namespace UltimatePushNotifications\admin\options\pages;

use UltimatePushNotifications\automation\AutoPush;
use UltimatePushNotifications\messaging\MergeTags;

/**
 * The Automations screen.
 *
 * Push on publish at the top (one Settings API form), then the event
 * automation rules — WooCommerce, BuddyPress, Contact Form 7, core — below
 * it, rendered by RulesPage.
 *
 * @package Options
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

class AutomationsPage {

	const GROUP = 'upn_automations';

	/**
	 * Register the setting so Settings API handles nonce, capability and save.
	 *
	 * @return void
	 */
	public static function register() {
		\register_setting(
			self::GROUP,
			AutoPush::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( AutoPush::class, 'sanitize' ),
				'default'           => AutoPush::defaults(),
			)
		);
	}

	/**
	 * Output the page.
	 *
	 * @return void
	 */
	public static function render() {
		$s     = AutoPush::settings();
		$types = \get_post_types( array( 'public' => true ), 'objects' );
		$roles = \wp_roles()->get_names();
		$tags  = MergeTags::grouped();
		$who   = isset( $s['audience']['who'] ) ? $s['audience']['who'] : 'any';
		$sel_roles = isset( $s['audience']['roles'] ) ? (array) $s['audience']['roles'] : array();
		?>
		<?php \UltimatePushNotifications\admin\builders\Layout::open( \__( 'Automations', 'ultimate-push-notifications' ), \__( 'Rules that send on their own when something happens on the site — an order, a comment, a form, a message.', 'ultimate-push-notifications' ), '', 'upn-automations' ); ?>
			<p class="description"><?php \esc_html_e( 'Notifications that send themselves. Each one runs through the same queue, log and health checks as a manual send.', 'ultimate-push-notifications' ); ?></p>

			<?php \settings_errors( self::GROUP ); ?>

			<form method="post" action="options.php">
				<?php \settings_fields( self::GROUP ); ?>

				<div class="section-title"><?php \esc_html_e( 'Push on publish', 'ultimate-push-notifications' ); ?></div>
				<p class="section-description"><?php \esc_html_e( 'When content is published, send it to your subscribers. Fires once per post — an edit or a republish does not send again.', 'ultimate-push-notifications' ); ?></p>

				<div class="upn-form">
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Status', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="checkbox" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[enabled]" value="1" <?php \checked( $s['enabled'] ); ?> /> <?php \esc_html_e( 'Enabled', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Post types', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><?php foreach ( $types as $type ) : ?>
								<?php if ( 'attachment' === $type->name ) { continue; } ?>
								<label style="display:inline-block;margin:0 1em .4em 0">
									<input type="checkbox" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[post_types][]" value="<?php echo \esc_attr( $type->name ); ?>" <?php \checked( \in_array( $type->name, $s['post_types'], true ) ); ?> />
									<?php echo \esc_html( $type->labels->singular_name ); ?>
								</label>
							<?php endforeach; ?></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-ap-title"><?php \esc_html_e( 'Title', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-ap-title" class="large-text" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[title]" value="<?php echo \esc_attr( $s['title'] ); ?>" /></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-ap-body"><?php \esc_html_e( 'Message', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-ap-body" class="large-text" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[body]" value="<?php echo \esc_attr( $s['body'] ); ?>" />
							<p class="description">
								<?php \esc_html_e( 'Merge tags:', 'ultimate-push-notifications' ); ?>
								<?php foreach ( $tags as $group => $items ) : ?>
									<?php foreach ( $items as $tag => $label ) : ?>
										<code title="<?php echo \esc_attr( $label ); ?>">{<?php echo \esc_html( $tag ); ?>}</code>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</p></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Image', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="checkbox" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[use_featured_image]" value="1" <?php \checked( $s['use_featured_image'] ); ?> /> <?php \esc_html_e( 'Show the featured image in the notification', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Send to', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="radio" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[audience][who]" value="any" <?php \checked( 'any', $who ); ?> /> <?php \esc_html_e( 'Everyone', 'ultimate-push-notifications' ); ?></label><br/>
							<label><input type="radio" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[audience][who]" value="anonymous" <?php \checked( 'anonymous', $who ); ?> /> <?php \esc_html_e( 'Visitors who are not logged in', 'ultimate-push-notifications' ); ?></label><br/>
							<label><input type="radio" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[audience][who]" value="logged_in" <?php \checked( 'logged_in', $who ); ?> /> <?php \esc_html_e( 'Logged-in users', 'ultimate-push-notifications' ); ?></label><br/>
							<label><input type="radio" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[audience][who]" value="roles" <?php \checked( 'roles', $who ); ?> /> <?php \esc_html_e( 'Specific roles:', 'ultimate-push-notifications' ); ?></label>
							<div style="margin:.4em 0 0 1.6em">
								<?php foreach ( $roles as $slug => $label ) : ?>
									<label style="display:inline-block;margin-right:1em"><input type="checkbox" name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[audience][roles][]" value="<?php echo \esc_attr( $slug ); ?>" <?php \checked( \in_array( $slug, $sel_roles, true ) ); ?> /> <?php echo \esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</div></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Delivery', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><?php \esc_html_e( 'Urgency', 'ultimate-push-notifications' ); ?>
								<select name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[urgency]">
									<option value="normal" <?php \selected( 'normal', $s['urgency'] ); ?>><?php \esc_html_e( 'Normal', 'ultimate-push-notifications' ); ?></option>
									<option value="high" <?php \selected( 'high', $s['urgency'] ); ?>><?php \esc_html_e( 'High', 'ultimate-push-notifications' ); ?></option>
									<option value="low" <?php \selected( 'low', $s['urgency'] ); ?>><?php \esc_html_e( 'Low', 'ultimate-push-notifications' ); ?></option>
								</select>
							</label>
							&nbsp;
							<label><?php \esc_html_e( 'Expires after', 'ultimate-push-notifications' ); ?>
								<select name="<?php echo \esc_attr( AutoPush::OPTION ); ?>[ttl]">
									<option value="3600" <?php \selected( 3600, $s['ttl'] ); ?>><?php \esc_html_e( '1 hour', 'ultimate-push-notifications' ); ?></option>
									<option value="86400" <?php \selected( 86400, $s['ttl'] ); ?>><?php \esc_html_e( '1 day', 'ultimate-push-notifications' ); ?></option>
									<option value="604800" <?php \selected( 604800, $s['ttl'] ); ?>><?php \esc_html_e( '1 week', 'ultimate-push-notifications' ); ?></option>
								</select>
							</label></div>
					</div>
				</div>

				<?php echo \UltimatePushNotifications\admin\builders\Layout::submit_bar( \get_submit_button() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
			</form>

			<hr/>
			<?php RulesPage::render_section(); ?>
		<?php \UltimatePushNotifications\admin\builders\Layout::close(); ?>
		<?php
	}

}
