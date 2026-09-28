<?php namespace UltimatePushNotifications\pro;

use UltimatePushNotifications\admin\builders\Layout;
use UltimatePushNotifications\admin\builders\Screens;

/**
 * The Pro screens, shown as tabs before Pro is installed.
 *
 * Each Pro screen has a tab in its group with a Pro badge; opening it shows
 * what the screen does, the site's own figure where the free plugin knows
 * one, and the upgrade link. When the Pro plugin is active it registers
 * the same slugs itself and these step aside — the tab is then the real
 * screen. Nothing here changes what the free plugin does.
 *
 * @package Pro
 * @since 1.6.2
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Adverts {

	/**
	 * @return void
	 */
	public static function register() {
		foreach ( self::screens() as $slug => $s ) {
			Screens::advertise( $slug, $s['label'], $s['group'], $s['order'], $s['title'], function () use ( $s ) {
				self::render( $s );
			} );
		}
	}

	/**
	 * The Pro screens, keyed by the slug Pro registers them under.
	 *
	 * @return array[]
	 */
	public static function screens() {
		$d = 'ultimate-push-notifications';
		return array(
			'cs-upn-scheduled'   => array( 'group' => 'compose', 'order' => 20, 'label' => \__( 'Scheduled', $d ), 'title' => \__( 'Scheduled Notifications', $d ), 'sub' => \__( 'Notifications booked for later, once or on a schedule.', $d ), 'key' => 'schedule', 'body' => \__( 'Write now, send at 9 am Tuesday — or every Friday — in each subscriber\'s own time zone. Booked as jobs on the same queue that sends everything else; pause, resume, cancel or send now.', $d ), 'points' => array( \__( 'One-off, daily, weekly or monthly', $d ), \__( 'Late runs are skipped, never sent stale', $d ), \__( 'Each schedule keeps its own delivery record', $d ) ) ),
			'cs-upn-ab'          => array( 'group' => 'compose', 'order' => 30, 'label' => \__( 'A/B Tests', $d ), 'title' => \__( 'A/B Tests', $d ), 'sub' => \__( 'Two versions of a notification; the better one goes to everyone else.', $d ), 'key' => 'ab', 'body' => \__( 'From Compose, add a second title, message or image. Each version goes to its own slice of the audience; after the wait, the version with the better click-through goes to the rest.', $d ), 'points' => array( \__( 'Slices are deterministic: nobody gets both', $d ), \__( 'Decide by click-through, clicks, or by hand', $d ), \__( 'Results kept on the A/B Tests screen', $d ) ) ),
			'cs-upn-welcome'     => array( 'group' => 'automations', 'order' => 20, 'label' => \__( 'Welcome Series', $d ), 'title' => \__( 'Welcome Series', $d ), 'sub' => \__( 'A short sequence for every new subscriber, at the delays you set.', $d ), 'key' => 'drip', 'body' => \__( 'New subscribers are most attentive in their first two days and currently hear nothing. Greet them: a thank-you now, your best content tomorrow, an offer on day three.', $d ), 'points' => array( \__( 'Up to five steps, each at its own delay, with merge tags', $d ), \__( 'For everyone, only visitors, or only members', $d ), \__( 'Stops on its own once they click', $d ) ) ),
			'cs-upn-store'       => array( 'group' => 'automations', 'order' => 30, 'label' => \__( 'Store Automations', $d ), 'title' => \__( 'Store Automations', $d ), 'sub' => \__( 'Abandoned carts, browsed products, back in stock and price drops, sent by themselves.', $d ), 'key' => 'commerce', 'body' => \__( 'Seven in ten carts are abandoned; a push reminder recovers 5–12% of them. Cart and browse reminders at your delays, a "tell me when it is back" button on out-of-stock products and a "tell me if the price drops" button on the rest — each shopper alerted only about what they asked for.', $d ), 'points' => array( \__( 'Reminders measured from the last time they touched the cart', $d ), \__( 'Buying or emptying the cart cancels what is waiting', $d ), \__( 'The order is credited to the notification', $d ) ) ),
			'cs-upn-delivery'    => array( 'group' => 'automations', 'order' => 40, 'label' => \__( 'Delivery Rules', $d ), 'title' => \__( 'Delivery Rules', $d ), 'sub' => \__( 'Quiet hours and frequency caps, applied to every subscriber as each delivery goes out.', $d ), 'key' => 'cadence', 'body' => \__( 'Six in ten people who turn notifications off do it over volume. Hold deliveries until the morning in each subscriber\'s own time zone, and never send more than N an hour or a day per person.', $d ), 'points' => array( \__( 'Held deliveries wait in the queue; nothing is lost', $d ), \__( 'Applies to the kinds of send you choose', $d ), \__( 'Digest delivery per automation: "5 new comments", not five alerts', $d ) ) ),
			'cs-upn-carriers'    => array( 'group' => 'automations', 'order' => 50, 'label' => \__( 'Carriers', $d ), 'title' => \__( 'Carriers', $d ), 'sub' => \__( 'Email, Slack, Discord, Telegram, SMS and WhatsApp — for your team and your customers, alongside push or when push cannot reach.', $d ), 'key' => 'carriers', 'body' => \__( 'Push is best-effort; an order alert needs a floor. Mirror an automation to your team\'s channels every time, or only when push has nobody to reach — and let customers get their order updates by SMS (through your own Android phone with httpSMS), WhatsApp or Telegram, when they ask for it.', $d ), 'points' => array( \__( 'Customers opt in at checkout or in their account; STOP always works', $d ), \__( 'Configured once, chosen per automation', $d ), \__( 'One fallback per notification, however many devices failed', $d ), \__( 'A test button per carrier', $d ) ) ),
			'cs-upn-segments'    => array( 'group' => 'subscribers', 'order' => 40, 'label' => \__( 'Segments', $d ), 'title' => \__( 'Segments', $d ), 'sub' => \__( 'Saved filters over your subscribers, for sends to just those people.', $d ), 'key' => 'segments', 'body' => \__( 'Targeted sends get about twice the click rate of broadcasts. Segments are saved filters over everything the subscriber list already shows — plus what only your database knows.', $d ), 'points' => array( \__( 'Locale, browser, OS, last seen, clicked recently', $d ), \__( 'WooCommerce: bought a category, spent over an amount, ordered in the last 90 days', $d ), \__( 'Membership level, BuddyPress group, subscribed from a page', $d ), \__( 'Seven built-in cohorts, and AI-proposed ones with your own key', $d ) ) ),
			'cs-upn-preferences' => array( 'group' => 'subscribers', 'order' => 50, 'label' => \__( 'Preference Centre', $d ), 'title' => \__( 'Preference Centre', $d ), 'sub' => \__( 'What subscribers can choose for themselves: categories to mute, and a pause.', $d ), 'key' => 'preferences', 'body' => \__( 'Let people choose instead of unsubscribing: which categories they want, and a pause for a day, a week or a month — from the bell, or a shortcode.', $d ), 'points' => array( \__( 'Categories on every send: Compose, automations, push on publish', $d ), \__( 'A muted category or a pause is honoured at delivery, per device', $d ), \__( 'Works for visitors without an account', $d ) ) ),
			'cs-upn-app'         => array( 'group' => 'optin', 'order' => 20, 'label' => \__( 'Home Screen App', $d ), 'title' => \__( 'Home Screen App', $d ), 'sub' => \__( 'Offer to install the site as an app, and count who did.', $d ), 'key' => 'pwa.install', 'body' => \__( 'On Android and desktop Chrome the browser can offer to install the site as an app — but only when asked from the page. Show your own install bar under the same delay and page-view rules as the soft-ask, and count who installed.', $d ), 'points' => array( \__( 'Your own wording; shown once the browser says the site is installable', $d ), \__( 'Installs counted per day, iPhone included', $d ), \__( 'Installed devices reachable as a segment', $d ) ) ),
			'cs-upn-monitor'     => array( 'group' => 'health', 'order' => 20, 'label' => \__( 'Monitor', $d ), 'title' => \__( 'Health Monitor', $d ), 'sub' => \__( 'The health check on a schedule, with alerts and one-click fixes.', $d ), 'key' => 'health.monitor', 'body' => \__( 'A one-off check tells you today; silent decay happens over months. Run the check daily, keep the snapshots, be told by email or webhook when the score drops or failures spike, and fix the usual causes with one click — or let it fix them.', $d ), 'points' => array( \__( 'Alerts de-duplicated per day', $d ), \__( 'Five bulk fixes with real counts; the safe ones can run by themselves', $d ), \__( 'A plain-English explanation with your own AI key', $d ) ) ),
			'cs-upn-history'     => array( 'group' => 'health', 'order' => 30, 'label' => \__( 'History', $d ), 'title' => \__( 'Send History', $d ), 'sub' => \__( 'Every send: what was delivered, what was clicked, and what it earned.', $d ), 'key' => 'analytics.history', 'body' => \__( 'The free plugin keeps seven days. Pro keeps everything, charts delivered and clicked per day, pages the list with click-through per send, and — with WooCommerce — credits orders and revenue to the notification that was clicked, exactly.', $d ), 'points' => array( \__( 'Retention forever by default, or a limit you set', $d ), \__( 'Revenue per send, net of refunds', $d ), \__( 'Opt-out risk before you send', $d ) ) ),
			'cs-upn-goals'       => array( 'group' => 'health', 'order' => 40, 'label' => \__( 'Goals', $d ), 'title' => \__( 'Goals', $d ), 'sub' => \__( 'Pages reached or events raised after a click, credited to the notification.', $d ), 'key' => 'analytics.goals', 'body' => \__( 'Not a store? Name what success is — a thank-you page, a sign-up, an event your code raises — and see it per send.', $d ), 'points' => array( \__( 'Up to ten goals, a path with * or a do_action', $d ), \__( 'Once per click, with an optional value', $d ), \__( 'Works without WooCommerce', $d ) ) ),
			'cs-upn-reports'     => array( 'group' => 'health', 'order' => 50, 'label' => \__( 'Reports', $d ), 'title' => \__( 'Reports', $d ), 'sub' => \__( 'The channel in numbers — emailed weekly or monthly, or printed for a client.', $d ), 'key' => 'reports', 'body' => \__( 'One page that says whether the channel is working and paying: sends, delivered, clicks, subscribers gained, revenue and goals, the health score, the most-clicked notifications. Emailed on a schedule, or opened as a print-ready page under your own name and logo.', $d ), 'points' => array( \__( 'Every Monday or on the 1st, to up to ten addresses', $d ), \__( 'Print view for 7 / 30 / 90 days', $d ), \__( 'Your name, link and logo', $d ) ) ),
			'cs-upn-ai'          => array( 'group' => 'settings', 'order' => 20, 'label' => \__( 'AI', $d ), 'title' => \__( 'AI Assistant', $d ), 'sub' => \__( 'Your own AI key for copy drafts, plain-language explanations and segment ideas.', $d ), 'key' => 'ai.copy', 'body' => \__( 'Writing well inside 38 and 174 characters is where people give up mid-campaign. Draft variants from a brief or a post, read the health report in plain words, discover segments — with your own OpenAI, Anthropic, Gemini, Groq, Mistral or Ollama key. No proxy, no markup.', $d ), 'points' => array( \__( 'Ten providers, keys never echoed back', $d ), \__( 'The model only writes text; it never sends', $d ), \__( 'Prompts grounded in your own content', $d ) ) ),
			'cs-upn-roles'       => array( 'group' => 'settings', 'order' => 30, 'label' => \__( 'Roles', $d ), 'title' => \__( 'Roles', $d ), 'sub' => \__( 'Who may compose, manage automations, manage segments, and view analytics.', $d ), 'key' => 'roles', 'body' => \__( 'Give an editor Compose, a marketer segments and history, a developer automations — without making anyone an administrator.', $d ), 'points' => array( \__( 'Four capabilities, ticked per role', $d ), \__( 'Administrators always have everything', $d ), \__( 'Settings stay with administrators', $d ) ) ),
			'cs-upn-whitelabel'  => array( 'group' => 'settings', 'order' => 40, 'label' => \__( 'White Label', $d ), 'title' => \__( 'White Label', $d ), 'sub' => \__( 'The product under your own name, for your clients.', $d ), 'key' => 'whitelabel', 'body' => \__( 'The menu, the Plugins list and the footer say what you choose; the vendor\'s links, the licence screen and update notices stay with your own users.', $d ), 'points' => array( \__( 'Menu label and icon, plugin name and author', $d ), \__( 'Named agency users keep the licence and updates', $d ), \__( 'Delivery is untouched', $d ) ) ),
			'cs-upn-config'      => array( 'group' => 'settings', 'order' => 50, 'label' => \__( 'Export / Import', $d ), 'title' => \__( 'Export / Import', $d ), 'sub' => \__( 'Carry this site\'s configuration to another — never keys, licence or subscribers.', $d ), 'key' => 'config', 'body' => \__( 'Roll one proven setup across a client portfolio: settings, automations, segments, templates, delivery rules and the rest in one file. Import adds by name; keys, licence, subscribers and history never move.', $d ), 'points' => array( \__( 'Fifteen sections, each optional', $d ), \__( 'Also over WP-CLI and the REST API', $d ), \__( 'On a multisite, copied between sites from one screen', $d ) ) ),
		);
	}

	/**
	 * @param array $s
	 * @return void
	 */
	public static function render( array $s ) {
		Layout::open( $s['title'], $s['sub'], '', 'upn-advert' );
		echo Locked::panel( array( 'title' => $s['title'], 'body' => $s['body'], 'points' => $s['points'], 'measure' => self::measure( $s['key'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		Layout::close();
	}

	/**
	 * A figure from this site, where the free plugin already has one.
	 *
	 * @param string $key
	 * @return string
	 */
	private static function measure( $key ) {
		$store = '\UltimatePushNotifications\transport\SubscriptionStore';
		$log   = '\UltimatePushNotifications\transport\DeliveryLog';
		try {
			switch ( $key ) {
				case 'segments':
				case 'preferences':
				case 'cadence':
					$n = \class_exists( $store ) ? (int) $store::count( 'webpush' ) : 0;
					return $n > 0 ? \sprintf( \_n( '%s subscriber on this site.', '%s subscribers on this site.', $n, 'ultimate-push-notifications' ), \number_format_i18n( $n ) ) : '';
				case 'drip':
					$n = \class_exists( $store ) ? (int) $store::count_since_days( 30 ) : 0;
					return $n > 0 ? \sprintf( \_n( '%s subscriber joined in the last 30 days.', '%s subscribers joined in the last 30 days.', $n, 'ultimate-push-notifications' ), \number_format_i18n( $n ) ) : '';
				case 'analytics.history':
				case 'reports':
				case 'ab':
					$s = \class_exists( $log ) ? $log::stats( 7 ) : array();
					return ! empty( $s['sends'] ) ? \sprintf( \__( 'Last 7 days: %1$s sends, %2$s delivered, %3$s clicks.', 'ultimate-push-notifications' ), \number_format_i18n( (int) $s['sends'] ), \number_format_i18n( (int) $s['success'] ), \number_format_i18n( (int) $s['clicks'] ) ) : '';
			}
		} catch ( \Throwable $e ) {
			return '';
		}
		return '';
	}

}
