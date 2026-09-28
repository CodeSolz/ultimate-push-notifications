<?php namespace UltimatePushNotifications\admin\options\pages;

/**
 * About Us: the plugin you are running, and the rest of the CodeSolz
 * plugins — the same page the other CodeSolz plugins carry.
 *
 * Always the last entry of the menu. Pro's white label hides it from a
 * client when the agency hides the vendor's links.
 *
 * @package Admin
 * @since 1.6.4
 * @author CodeSolz <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\pro\Upgrade;

class AboutUsPage {

	const PAGE = 'cs-upn-about-us';
	const CAP  = 'manage_options';

	/** UTM tags on every outbound link, so codesolz.net knows the click came from here. */
	const UTM = 'utm_source=upn-about&utm_medium=wp-admin&utm_campaign=about-page';

	/**
	 * @return void
	 */
	public static function boot() {
		// After the grouped entries (50) and Upgrade to Pro (60): always last.
		\add_action( 'admin_menu', array( __CLASS__, 'register' ), 61 );
	}

	/**
	 * @return void
	 */
	public static function register() {
		if ( ! \defined( 'CS_UPN_PLUGIN_IDENTIFIER' ) ) {
			return;
		}
		$hook = \add_submenu_page( CS_UPN_PLUGIN_IDENTIFIER, \__( 'About Us', 'ultimate-push-notifications' ), \__( 'About Us', 'ultimate-push-notifications' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		if ( \is_string( $hook ) && '' !== $hook ) {
			\add_action( 'load-' . $hook, array( __CLASS__, 'on_load' ) );
		}
	}

	/**
	 * @return void
	 */
	public static function on_load() {
		\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/**
	 * @return void
	 */
	public static function styles() {
		\wp_enqueue_style( 'upn-about', CS_UPN_PLUGIN_ASSET_URI . 'css/upn-about.css', array(), CS_UPN_VERSION );
	}

	/**
	 * @param string $url
	 * @return string with the UTM tags
	 */
	private static function tag( $url ) {
		return $url . ( false === \strpos( $url, '?' ) ? '?' : '&' ) . self::UTM;
	}

	/**
	 * The other CodeSolz plugins. An entry without icon_file shows its emoji.
	 *
	 * @return array[]
	 */
	public static function plugins() {
		$d = 'ultimate-push-notifications';
		return array(
			array(
				'title'       => 'WatchSpire',
				'subtitle'    => \__( 'Your WordPress early warning system', $d ),
				'description' => \__( 'Catch silent WordPress failures before they cost you leads or sales. WatchSpire monitors forms, checkout, email delivery, uptime, SSL, and more, then alerts you when something goes wrong.', $d ),
				'icon_file'   => 'watchspire.png',
				'features'    => array( \__( 'Catch failed forms & checkout issues', $d ), \__( 'Know what changed before a failure', $d ), \__( 'See which update may have caused a failure', $d ) ),
				'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/watchspire/',
				'wporg_url'   => 'https://wordpress.org/plugins/watchspire/',
			),
			array(
				'title'       => 'Better Find & Replace',
				'subtitle'    => \__( 'Find and replace anything, safely', $d ),
				'description' => \__( 'Replace any text across your site in real time or permanently in the database — plain text, regular expressions, media file swaps and AI suggestions, with a preview before anything is written.', $d ),
				'icon'        => '🔍',
				'features'    => array( \__( 'Real-time text masking & replacement', $d ), \__( 'Database find & replace with restore', $d ), \__( 'Media replacer and AI suggestions', $d ) ),
				'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/',
				'wporg_url'   => 'https://wordpress.org/plugins/real-time-auto-find-and-replace/',
			),
			array(
				'title'       => 'AI Store Signals for WooCommerce',
				'subtitle'    => \__( 'Get your products ready for AI shopping', $d ),
				'description' => \__( 'See how well AI systems can understand your WooCommerce store. Find visibility gaps, improve product data, generate AI-friendly feeds and llms.txt, and track AI bot activity.', $d ),
				'icon_file'   => 'ai-store-signals.png',
				'features'    => array( \__( 'Get an AI readiness score for your store', $d ), \__( 'Find product & schema visibility gaps', $d ), \__( 'Generate AI-friendly feeds & llms.txt', $d ) ),
				'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/ai-store-signals-for-woocommerce/',
				'wporg_url'   => 'https://wordpress.org/plugins/ai-store-signals-for-woocommerce/',
			),
			array(
				'title'       => 'AttributeHub for WooCommerce',
				'subtitle'    => \__( 'Turn messy product attributes into clean filters', $d ),
				'description' => \__( 'Clean up confusing supplier and imported attribute values without changing your original product data. Create consistent, customer-friendly filters across your store.', $d ),
				'icon_file'   => 'attributehub.png',
				'features'    => array( \__( 'Turn codes like BK into Black and WH into White', $d ), \__( 'Clean imported attributes automatically', $d ), \__( 'Keep original product data untouched', $d ) ),
				'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/attributehub-for-woocommerce/',
				'wporg_url'   => 'https://wordpress.org/plugins/attributehub-for-woocommerce/',
			),
			array(
				'title'       => 'Merchant Feed Booster for WooCommerce',
				'subtitle'    => \__( 'Fix Google Shopping feed problems before they cost sales', $d ),
				'description' => \__( 'Find exactly why your WooCommerce products may be rejected or underperforming on Google Shopping. Audit your catalog, get a health score, and see exactly what needs fixing.', $d ),
				'icon_file'   => 'merchant-feed-booster.png',
				'features'    => array( \__( 'Audit products against 27 feed rules', $d ), \__( 'Get a 0–100 feed health score', $d ), \__( 'See exactly what needs fixing', $d ) ),
				'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugins/',
				'wporg_url'   => 'https://wordpress.org/plugins/merchant-feed-booster-lite-for-woocommerce/',
			),
		);
	}

	/**
	 * @return void
	 */
	public static function render() {
		$d             = 'ultimate-push-notifications';
		$products_url  = self::tag( 'https://codesolz.net/our-products/wordpress-plugins/' );
		$docs_url      = self::tag( 'https://docs.codesolz.net/ultimate-push-notifications/' );
		$website_url   = self::tag( 'https://www.codesolz.net/' );
		$support_url   = self::tag( 'https://codesolz.net/' );
		$community_url = self::tag( 'https://codesolz.net/forum/' );
		$wporg_url     = 'https://wordpress.org/plugins/ultimate-push-notifications/';
		$pro           = Upgrade::pro_active();
		?>
		<div class="wrap">
		<div class="upn-about-wrap">

			<div class="upn-about-hero">
				<div class="upn-about-hero-grid">
					<div class="upn-about-hero-copy">
						<div class="upn-about-hero-badge"><?php \esc_html_e( 'Premium WordPress Plugin Suite', $d ); ?></div>
						<h1 class="upn-about-hero-title"><?php \esc_html_e( 'Tools That Make WordPress Work Faster, Smarter, and Better', $d ); ?></h1>
						<p class="upn-about-hero-subtitle"><?php \esc_html_e( 'CodeSolz builds premium-quality plugins for real websites. From push notifications and customer messaging to safer search-and-replace, AI-ready stores and WooCommerce enhancements, every product is crafted to save time and deliver clean results.', $d ); ?></p>
						<div class="upn-about-hero-points">
							<span class="upn-about-hero-point"><?php \esc_html_e( 'Built for agencies, publishers, and store owners', $d ); ?></span>
							<span class="upn-about-hero-point"><?php \esc_html_e( 'Self-hosted: your data stays on your site', $d ); ?></span>
							<span class="upn-about-hero-point"><?php \esc_html_e( 'Performance-focused tools without the bloat', $d ); ?></span>
						</div>
						<div class="upn-about-hero-actions">
							<a href="<?php echo \esc_url( $products_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-hero-cta"><?php \esc_html_e( 'Browse Our Plugins', $d ); ?> <span aria-hidden="true">&#8599;</span></a>
							<a href="<?php echo \esc_url( $website_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-hero-link"><?php \esc_html_e( 'Visit CodeSolz', $d ); ?></a>
						</div>
					</div>
					<div class="upn-about-hero-showcase">
						<div class="upn-about-hero-panel">
							<div class="upn-about-hero-panel-label"><?php \esc_html_e( 'Why Users Explore More', $d ); ?></div>
							<h2 class="upn-about-hero-panel-title"><?php \esc_html_e( 'A focused toolkit for modern WordPress teams', $d ); ?></h2>
							<div class="upn-about-hero-feature-list">
								<div class="upn-about-hero-feature"><strong><?php \esc_html_e( 'Reach and Engagement', $d ); ?></strong><span><?php \esc_html_e( 'Push notifications, SMS, WhatsApp and Telegram that bring people back the moment something happens.', $d ); ?></span></div>
								<div class="upn-about-hero-feature"><strong><?php \esc_html_e( 'Safe Site Cleanup', $d ); ?></strong><span><?php \esc_html_e( 'Handle search, replace, and content updates with tools built for real production work.', $d ); ?></span></div>
								<div class="upn-about-hero-feature"><strong><?php \esc_html_e( 'Growth and Organization', $d ); ?></strong><span><?php \esc_html_e( 'Improve product data, monitoring, and WooCommerce experiences from one ecosystem.', $d ); ?></span></div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="upn-about-section-header">
				<div class="upn-about-section-label"><?php \esc_html_e( 'Currently Installed', $d ); ?></div>
				<h2 class="upn-about-section-title"><?php \esc_html_e( 'Your Active Plugin', $d ); ?></h2>
				<p class="upn-about-section-desc"><?php \esc_html_e( 'You are running Ultimate Push Notifications. Here is a quick overview of everything it can do.', $d ); ?></p>
			</div>

			<div class="upn-about-featured-card">
				<div class="upn-about-featured-inner">
					<div class="upn-about-featured-icon-wrap" aria-hidden="true">🔔</div>
					<div class="upn-about-featured-body">
						<div class="upn-about-featured-meta">
							<span class="upn-about-badge featured-label"><?php \esc_html_e( 'Featured', $d ); ?></span>
							<span class="upn-about-badge active"><?php \esc_html_e( 'Active', $d ); ?></span>
							<?php if ( $pro ) : ?><span class="upn-about-badge premium"><?php \esc_html_e( 'Pro', $d ); ?></span><?php endif; ?>
						</div>
						<h2 class="upn-about-featured-title"><?php \esc_html_e( 'Ultimate Push Notifications', $d ); ?></h2>
						<p class="upn-about-featured-desc"><?php \esc_html_e( 'Self-hosted web push for WordPress: unlimited subscribers, a composer, and automations for WooCommerce, BuddyPress, membership plugins and forms — one click to set up, no account with anyone, no per-subscriber fees.', $d ); ?></p>
						<ul class="upn-about-feature-list">
							<li><?php \esc_html_e( 'One-click Web Push (VAPID) setup', $d ); ?></li>
							<li><?php \esc_html_e( 'Unlimited subscribers, visitors included', $d ); ?></li>
							<li><?php \esc_html_e( 'Compose with a live recipient count', $d ); ?></li>
							<li><?php \esc_html_e( '29 automation triggers', $d ); ?></li>
							<li><?php \esc_html_e( 'Soft-ask prompt, bell and block', $d ); ?></li>
							<li><?php \esc_html_e( 'Health checks for every link in the chain', $d ); ?></li>
						</ul>
						<div class="upn-about-btn-group">
							<a href="<?php echo \esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn primary"><?php \esc_html_e( 'Documentation', $d ); ?></a>
							<a href="<?php echo \esc_url( $wporg_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn secondary"><?php \esc_html_e( 'WordPress.org Page', $d ); ?></a>
							<?php if ( ! $pro ) : ?>
								<a href="<?php echo \esc_url( Upgrade::url( 'about-page', 'about-page' ) ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn outline-purple">&#9733; <?php \esc_html_e( 'Upgrade to Pro', $d ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="upn-about-section-header">
				<div class="upn-about-section-label"><?php \esc_html_e( 'Plugin Ecosystem', $d ); ?></div>
				<h2 class="upn-about-section-title"><?php \esc_html_e( 'More Smart WordPress Tools', $d ); ?></h2>
				<p class="upn-about-section-desc"><?php \esc_html_e( 'Explore our other plugins — all built with the same commitment to quality, simplicity, and real-world value.', $d ); ?></p>
			</div>

			<div class="upn-about-plugins-grid">
				<?php foreach ( self::plugins() as $plugin ) : ?>
				<div class="upn-about-plugin-card">
					<div class="upn-about-card-top">
						<div class="upn-about-card-icon" aria-hidden="true">
							<?php if ( ! empty( $plugin['icon_file'] ) ) : ?>
								<img src="<?php echo \esc_url( CS_UPN_PLUGIN_ASSET_URI . 'img/plugins/' . $plugin['icon_file'] ); ?>" alt="" width="48" height="48" loading="lazy" />
							<?php else : ?>
								<span style="font-size:24px"><?php echo \esc_html( $plugin['icon'] ); ?></span>
							<?php endif; ?>
						</div>
						<div class="upn-about-card-heading">
							<h3 class="upn-about-card-title"><?php echo \esc_html( $plugin['title'] ); ?></h3>
							<span class="upn-about-card-subtitle"><?php echo \esc_html( $plugin['subtitle'] ); ?></span>
						</div>
					</div>
					<p class="upn-about-card-desc"><?php echo \esc_html( $plugin['description'] ); ?></p>
					<ul class="upn-about-card-features">
						<?php foreach ( $plugin['features'] as $feature ) : ?><li><?php echo \esc_html( $feature ); ?></li><?php endforeach; ?>
					</ul>
					<div class="upn-about-card-footer">
						<a href="<?php echo \esc_url( self::tag( $plugin['pro_url'] ) ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-card-link pro"><?php \esc_html_e( 'Learn More', $d ); ?></a>
						<a href="<?php echo \esc_url( $plugin['wporg_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-card-link wporg"><?php \esc_html_e( 'Get It Free on WordPress.org', $d ); ?> &#8599;</a>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="upn-about-trust-section">
				<div class="upn-about-trust-logo">Code<span>Solz</span></div>
				<h2 class="upn-about-trust-tagline"><?php \esc_html_e( 'Building WordPress Tools Developers Trust', $d ); ?></h2>
				<p class="upn-about-trust-desc"><?php \esc_html_e( 'Since 2016, CodeSolz has been crafting practical, lightweight, and reliable WordPress plugins. Our focus is simple: powerful features with zero unnecessary bloat.', $d ); ?></p>
				<div class="upn-about-trust-links">
					<a href="<?php echo \esc_url( $website_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn primary"><?php \esc_html_e( 'Visit Website', $d ); ?></a>
					<a href="<?php echo \esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn secondary"><?php \esc_html_e( 'Documentation', $d ); ?></a>
					<a href="<?php echo \esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn secondary"><?php \esc_html_e( 'Get Support', $d ); ?></a>
					<a href="<?php echo \esc_url( $community_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn secondary"><?php \esc_html_e( 'Our Community', $d ); ?></a>
					<a href="<?php echo \esc_url( $products_url ); ?>" target="_blank" rel="noopener noreferrer" class="upn-about-btn outline-purple"><?php \esc_html_e( 'All Our Plugins', $d ); ?> &#8599;</a>
				</div>
			</div>

		</div>
		</div>
		<?php
	}

}
