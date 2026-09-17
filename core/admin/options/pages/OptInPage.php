<?php namespace UltimatePushNotifications\admin\options\pages;

use UltimatePushNotifications\optin\OptIn;
use UltimatePushNotifications\pwa\Manifest;
use UltimatePushNotifications\pro\Locked;
use UltimatePushNotifications\transport\SubscriptionStore;

/**
 * The Subscribe Prompt screen.
 *
 * @package Options
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

class OptInPage {

	const GROUP = 'upn_optin_group';

	public static function register() {
		\register_setting(
			self::GROUP,
			OptIn::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( OptIn::class, 'sanitize' ),
				'default'           => OptIn::defaults(),
			)
		);
		\register_setting(
			self::GROUP,
			Manifest::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Manifest::class, 'sanitize' ),
				'default'           => Manifest::defaults(),
			)
		);
	}

	public static function render() {
		$s = OptIn::settings();
		$n = OptIn::OPTION;
		$p = Manifest::settings();
		$m = Manifest::OPTION;
		?>
		<?php \UltimatePushNotifications\admin\builders\Layout::open( \__( 'Subscribe Prompt', 'ultimate-push-notifications' ), \__( 'How visitors are invited to subscribe: the soft-ask bar, the floating bell, and the Home Screen app.', 'ultimate-push-notifications' ), '', 'upn-optin-settings' ); ?>
			<div class="well"><p>
				<?php \esc_html_e( 'How visitors are invited to subscribe. The browser only lets a site ask for permission once — a visitor who says no is gone for months — so the plugin asks with its own dismissible bar first and only shows the native prompt when they click Allow.', 'ultimate-push-notifications' ); ?>
			</p></div>

			<?php \settings_errors( self::GROUP ); ?>

			<form method="post" action="options.php">
				<?php \settings_fields( self::GROUP ); ?>

				<div class="section-title"><?php \esc_html_e( 'Soft-ask bar', 'ultimate-push-notifications' ); ?></div>
				<div class="upn-form">
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Status', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="checkbox" name="<?php echo \esc_attr( $n ); ?>[prompt_enabled]" value="1" <?php \checked( $s['prompt_enabled'] ); ?> /> <?php \esc_html_e( 'Show the bar to visitors who have not subscribed', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Show to', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><?php /* Hidden fallbacks: an unticked box is absent from POST, and absent means "keep default" to the sanitizer. */ ?>
							<input type="hidden" name="<?php echo \esc_attr( $n ); ?>[prompt_anonymous]" value="0" />
							<label><input type="checkbox" name="<?php echo \esc_attr( $n ); ?>[prompt_anonymous]" value="1" <?php \checked( $s['prompt_anonymous'] ); ?> /> <?php \esc_html_e( 'Visitors who are not logged in', 'ultimate-push-notifications' ); ?></label><br/>
							<input type="hidden" name="<?php echo \esc_attr( $n ); ?>[prompt_logged_in]" value="0" />
							<label><input type="checkbox" name="<?php echo \esc_attr( $n ); ?>[prompt_logged_in]" value="1" <?php \checked( $s['prompt_logged_in'] ); ?> /> <?php \esc_html_e( 'Logged-in users', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-oi-title"><?php \esc_html_e( 'Title', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-oi-title" class="regular-text" name="<?php echo \esc_attr( $n ); ?>[prompt_title]" value="<?php echo \esc_attr( $s['prompt_title'] ); ?>" maxlength="80" /></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-oi-text"><?php \esc_html_e( 'Message', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-oi-text" class="large-text" name="<?php echo \esc_attr( $n ); ?>[prompt_text]" value="<?php echo \esc_attr( $s['prompt_text'] ); ?>" maxlength="240" /></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Buttons', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" class="regular-text" name="<?php echo \esc_attr( $n ); ?>[prompt_allow]" value="<?php echo \esc_attr( $s['prompt_allow'] ); ?>" maxlength="30" placeholder="Allow" />
							<input type="text" class="regular-text" name="<?php echo \esc_attr( $n ); ?>[prompt_later]" value="<?php echo \esc_attr( $s['prompt_later'] ); ?>" maxlength="30" placeholder="Not now" /></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'When to show it', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><?php \esc_html_e( 'After', 'ultimate-push-notifications' ); ?> <input type="number" min="0" max="300" class="small-text" name="<?php echo \esc_attr( $n ); ?>[prompt_delay]" value="<?php echo (int) $s['prompt_delay']; ?>" /> <?php \esc_html_e( 'seconds on the page', 'ultimate-push-notifications' ); ?></label><br/>
							<label><?php \esc_html_e( 'From the visitor\'s', 'ultimate-push-notifications' ); ?> <input type="number" min="1" max="20" class="small-text" name="<?php echo \esc_attr( $n ); ?>[prompt_pageviews]" value="<?php echo (int) $s['prompt_pageviews']; ?>" /> <?php \esc_html_e( 'page view onwards', 'ultimate-push-notifications' ); ?></label><br/>
							<label><?php \esc_html_e( 'After scrolling', 'ultimate-push-notifications' ); ?> <input type="number" min="0" max="100" class="small-text" name="<?php echo \esc_attr( $n ); ?>[prompt_scroll]" value="<?php echo (int) $s['prompt_scroll']; ?>" /> <?php \esc_html_e( '% of the page (0 = do not wait for scroll)', 'ultimate-push-notifications' ); ?></label>
							<p class="description"><?php \esc_html_e( 'Second page view and a few seconds in is a good default: it skips bounces and catches people who are actually reading.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'If dismissed', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><?php \esc_html_e( 'Do not ask again for', 'ultimate-push-notifications' ); ?> <input type="number" min="1" max="365" class="small-text" name="<?php echo \esc_attr( $n ); ?>[prompt_dismiss_days]" value="<?php echo (int) $s['prompt_dismiss_days']; ?>" /> <?php \esc_html_e( 'days', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Position', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="radio" name="<?php echo \esc_attr( $n ); ?>[prompt_position]" value="bottom" <?php \checked( 'bottom', $s['prompt_position'] ); ?> /> <?php \esc_html_e( 'Bottom of the screen', 'ultimate-push-notifications' ); ?></label><br/>
							<label><input type="radio" name="<?php echo \esc_attr( $n ); ?>[prompt_position]" value="top" <?php \checked( 'top', $s['prompt_position'] ); ?> /> <?php \esc_html_e( 'Top of the screen', 'ultimate-push-notifications' ); ?></label></div>
					</div>
				</div>

				<div class="section-title"><?php \esc_html_e( 'Floating bell', 'ultimate-push-notifications' ); ?></div>
				<div class="upn-form">
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Status', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="checkbox" name="<?php echo \esc_attr( $n ); ?>[bell_enabled]" value="1" <?php \checked( $s['bell_enabled'] ); ?> /> <?php \esc_html_e( 'Show a bell button on every page. Subscribed visitors can use it to turn notifications off.', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Position', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="radio" name="<?php echo \esc_attr( $n ); ?>[bell_position]" value="bottom-right" <?php \checked( 'bottom-right', $s['bell_position'] ); ?> /> <?php \esc_html_e( 'Bottom right', 'ultimate-push-notifications' ); ?></label>&nbsp;&nbsp;
							<label><input type="radio" name="<?php echo \esc_attr( $n ); ?>[bell_position]" value="bottom-left" <?php \checked( 'bottom-left', $s['bell_position'] ); ?> /> <?php \esc_html_e( 'Bottom left', 'ultimate-push-notifications' ); ?></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-oi-color"><?php \esc_html_e( 'Colour', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="color" id="upn-oi-color" name="<?php echo \esc_attr( $n ); ?>[bell_color]" value="<?php echo \esc_attr( $s['bell_color'] ); ?>" /></div>
					</div>
				</div>

				<div class="section-title"><?php \esc_html_e( 'Home Screen app', 'ultimate-push-notifications' ); ?></div>
				<p class="section-description"><?php \esc_html_e( 'On iPhone and iPad, notifications only work for a site added to the Home Screen, and that needs a web app manifest. Turn this on and the plugin serves one (using your Site Icon), tags every page for installation, and shows iPhone visitors how to add the site instead of a prompt they cannot act on.', 'ultimate-push-notifications' ); ?></p>
				<?php if ( Manifest::deferred() ) : ?><p class="description"><strong><?php \esc_html_e( 'Another plugin already provides a manifest, so this one stays out of the way. The iPhone guidance below is still used.', 'ultimate-push-notifications' ); ?></strong></p><?php endif; ?>
				<?php if ( \function_exists( 'has_site_icon' ) && ! \has_site_icon() ) : ?><p class="description"><strong><?php \esc_html_e( 'Set a Site Icon (Appearance → Customize → Site Identity, at least 512×512) — without one the site cannot be installed.', 'ultimate-push-notifications' ); ?></strong></p><?php endif; ?>
				<div class="upn-form">
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Status', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="checkbox" name="<?php echo \esc_attr( $m ); ?>[enabled]" value="1" <?php \checked( $p['enabled'] ); ?> /> <?php \esc_html_e( 'Make the site installable', 'ultimate-push-notifications' ); ?></label> <span class="description"><?php echo \esc_html( \sprintf( \__( 'Manifest: %s', 'ultimate-push-notifications' ), Manifest::url() ) ); ?></span></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-pwa-short"><?php \esc_html_e( 'App name', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-pwa-short" class="regular-text" name="<?php echo \esc_attr( $m ); ?>[short_name]" value="<?php echo \esc_attr( $p['short_name'] ); ?>" maxlength="12" placeholder="<?php echo \esc_attr( Manifest::short_name() ); ?>" /> <span class="description"><?php \esc_html_e( 'Under the icon; 12 characters at most. Empty = the site title.', 'ultimate-push-notifications' ); ?></span></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Colours', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><?php \esc_html_e( 'Theme', 'ultimate-push-notifications' ); ?> <input type="color" name="<?php echo \esc_attr( $m ); ?>[theme_color]" value="<?php echo \esc_attr( $p['theme_color'] ); ?>" /></label>&nbsp;&nbsp;<label><?php \esc_html_e( 'Splash background', 'ultimate-push-notifications' ); ?> <input type="color" name="<?php echo \esc_attr( $m ); ?>[background_color]" value="<?php echo \esc_attr( $p['background_color'] ); ?>" /></label></div>
					</div>
					<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Opens as', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><select name="<?php echo \esc_attr( $m ); ?>[display]"><?php foreach ( array( 'standalone' => \__( 'An app (no browser chrome)', 'ultimate-push-notifications' ), 'minimal-ui' => \__( 'An app with back and reload', 'ultimate-push-notifications' ), 'browser' => \__( 'A browser tab', 'ultimate-push-notifications' ) ) as $k => $label ) : ?><option value="<?php echo \esc_attr( $k ); ?>" <?php \selected( $k, $p['display'] ); ?>><?php echo \esc_html( $label ); ?></option><?php endforeach; ?></select></div>
					</div>
					<div class="form-group">
						<div class="label"><label for="upn-pwa-ios-title"><?php \esc_html_e( 'iPhone guidance', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-pwa-ios-title" class="regular-text" name="<?php echo \esc_attr( $m ); ?>[ios_title]" value="<?php echo \esc_attr( $p['ios_title'] ); ?>" maxlength="80" /><br/>
							<input type="text" class="large-text" name="<?php echo \esc_attr( $m ); ?>[ios_text]" value="<?php echo \esc_attr( $p['ios_text'] ); ?>" maxlength="240" /><br/>
							<input type="text" class="small-text" style="width:12em" name="<?php echo \esc_attr( $m ); ?>[ios_ok]" value="<?php echo \esc_attr( $p['ios_ok'] ); ?>" maxlength="30" />
							<p class="description"><?php \esc_html_e( 'Shown in the soft-ask bar to iPhone and iPad visitors who have not added the site yet, under the same delay and page-view rules.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
				</div>

				<div class="section-title"><?php \esc_html_e( 'Button anywhere', 'ultimate-push-notifications' ); ?></div>
				<p>
					<?php \esc_html_e( 'Shortcode:', 'ultimate-push-notifications' ); ?> <code>[upn_subscribe text="Get notifications" subscribed_text="Notifications on"]</code><br/>
					<?php \esc_html_e( 'Block:', 'ultimate-push-notifications' ); ?> <strong><?php \esc_html_e( 'Push Subscribe Button', 'ultimate-push-notifications' ); ?></strong> <?php \esc_html_e( 'in the Widgets category.', 'ultimate-push-notifications' ); ?><br/>
					<?php \esc_html_e( 'Any element:', 'ultimate-push-notifications' ); ?> <code>&lt;a href="#" data-upn-subscribe&gt;…&lt;/a&gt;</code>
				</p>

				<?php echo \UltimatePushNotifications\admin\builders\Layout::submit_bar( \get_submit_button() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
			</form>

			<?php if ( ! Locked::unlocked( 'pwa.install' ) ) : ?>
				<?php
				echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
					'title'  => \__( 'Install prompt', 'ultimate-push-notifications' ),
					'body'   => \__( 'On Android and desktop Chrome the browser can offer to install the site as an app — but only when asked from the page. Show your own install bar under the same delay and page-view rules as the soft-ask, and count who installed.', 'ultimate-push-notifications' ),
					'points' => array(
						\__( 'Your own wording; shown once the browser says the site is installable', 'ultimate-push-notifications' ),
						\__( 'Installs counted per day on the Health page and in reports', 'ultimate-push-notifications' ),
						\__( 'Installed visitors reachable as a segment', 'ultimate-push-notifications' ),
					),
				) );
				?>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'preferences' ) ) : ?>
				<?php
				echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
					'title'  => \__( 'Preference centre', 'ultimate-push-notifications' ),
					'body'   => \__( 'Most people who turn notifications off do it over volume. Let them choose instead: which categories they want, and a pause for a day, a week or a month — from the bell, or a [upn_preferences] shortcode.', 'ultimate-push-notifications' ),
					'points' => array(
						\__( 'Categories on every send: Compose, automations, push on publish', 'ultimate-push-notifications' ),
						\__( 'A muted category or a pause is honoured at delivery, per device', 'ultimate-push-notifications' ),
						\__( 'Works for visitors without an account', 'ultimate-push-notifications' ),
					),
				) );
				?>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'drip' ) ) : ?>
				<?php
				echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
					'title'   => \__( 'Welcome series', 'ultimate-push-notifications' ),
					'body'    => \__( 'New subscribers are most attentive in their first two days and currently hear nothing. Greet them with a short sequence: a thank-you now, your best content tomorrow, an offer on day three.', 'ultimate-push-notifications' ),
					'points'  => array(
						\__( 'Up to five steps, each at its own delay, with merge tags', 'ultimate-push-notifications' ),
						\__( 'For everyone, only visitors, or only members', 'ultimate-push-notifications' ),
						\__( 'Stops on its own once they click', 'ultimate-push-notifications' ),
					),
					'measure' => \sprintf( \_n( '%s subscriber joined in the last 30 days.', '%s subscribers joined in the last 30 days.', SubscriptionStore::count_since_days( 30 ), 'ultimate-push-notifications' ), \number_format_i18n( SubscriptionStore::count_since_days( 30 ) ) ),
				) );
				?>
			<?php endif; ?>
		<?php \UltimatePushNotifications\admin\builders\Layout::close(); ?>
		<?php
	}

}
