<?php namespace UltimatePushNotifications\pro;

/**
 * Where to get Pro: an "Upgrade to Pro" entry at the end of the menu and a
 * link on the Plugins screen, both straight to the product page (in a new
 * tab), both gone once Pro is active.
 *
 * Every link carries where it was clicked (utm_source) so the sales page can
 * tell a menu click from a Plugins-screen click from a locked panel.
 *
 * @package Pro
 * @since 1.6.4
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Upgrade {

	const SALES_URL = 'https://codesolz.net/our-products/wordpress-plugin/ultimate-push-notifications/';

	/** The menu entry's slug until it is pointed at the sales page. */
	const MENU_SLUG = 'cs-upn-go-pro';

	/**
	 * @return void
	 */
	public static function boot() {
		// After Screens::emit() (50), so the entry is last; before the rewrite.
		\add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		\add_action( 'admin_menu', array( __CLASS__, 'point_menu_at_sales_page' ), 9999 );
		\add_action( 'admin_footer', array( __CLASS__, 'menu_new_tab' ) );
		\add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
	}

	/**
	 * Is Pro installed and running? Asked only to decide whether to show the
	 * way to buy it — never to unlock anything (Entitlements does that).
	 *
	 * @return bool
	 */
	public static function pro_active() {
		/**
		 * @param bool $active
		 */
		return (bool) \apply_filters( 'upn_pro_active', \defined( 'CS_UPNP_VERSION' ) );
	}

	/**
	 * The product page, tagged with where the click came from.
	 *
	 * @param string $source   utm_source, e.g. wp-menu, plugins-list
	 * @param string $campaign utm_campaign
	 * @return string
	 */
	public static function url( $source, $campaign = 'gopro' ) {
		$url = \add_query_arg(
			array(
				'utm_source'   => \rawurlencode( (string) $source ),
				'utm_medium'   => 'wp-dash',
				'utm_campaign' => \rawurlencode( (string) $campaign ),
			),
			self::SALES_URL
		);
		/**
		 * The upgrade link, so a bundle or reseller can point it elsewhere.
		 *
		 * @param string $url
		 * @param string $source
		 */
		return (string) \apply_filters( 'upn_upgrade_url', $url, (string) $source );
	}

	/**
	 * @return void
	 */
	public static function menu() {
		if ( self::pro_active() || ! \defined( 'CS_UPN_PLUGIN_IDENTIFIER' ) ) {
			return;
		}
		\add_submenu_page(
			CS_UPN_PLUGIN_IDENTIFIER,
			\__( 'Upgrade to Pro', 'ultimate-push-notifications' ),
			'<span class="upn-go-pro" style="color:#f0b849;font-weight:600"><span class="dashicons dashicons-star-filled" style="font-size:16px;width:16px;height:16px;vertical-align:text-top"></span> ' . \esc_html__( 'Upgrade to Pro', 'ultimate-push-notifications' ) . '</span>',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'redirect' )
		);
	}

	/**
	 * Swap the entry's slug for the product page, so the click goes straight there.
	 *
	 * @return void
	 */
	public static function point_menu_at_sales_page() {
		global $submenu;
		if ( ! \defined( 'CS_UPN_PLUGIN_IDENTIFIER' ) || empty( $submenu[ CS_UPN_PLUGIN_IDENTIFIER ] ) ) {
			return;
		}
		foreach ( $submenu[ CS_UPN_PLUGIN_IDENTIFIER ] as $i => $item ) {
			if ( isset( $item[2] ) && self::MENU_SLUG === $item[2] ) {
				$submenu[ CS_UPN_PLUGIN_IDENTIFIER ][ $i ][2] = self::url( 'wp-menu' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- pointing our own entry at an external page
				break;
			}
		}
	}

	/**
	 * Open the menu entry in a new tab.
	 *
	 * @return void
	 */
	public static function menu_new_tab() {
		if ( self::pro_active() || ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var a = document.querySelector( '#adminmenu a[href="' + <?php echo \wp_json_encode( self::url( 'wp-menu' ) ); ?>.replace( /"/g, '\\"' ) + '"]' );
			if ( a ) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
		} )();
		</script>
		<?php
	}

	/**
	 * admin.php?page=cs-upn-go-pro opened directly (a bookmark, JavaScript off): on to the product page.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( isset( $_GET['page'] ) && self::MENU_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- compared to a constant, read only
			\wp_redirect( self::url( 'wp-menu-direct' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- our own product page
			exit;
		}
	}

	/**
	 * The page callback, should the redirect not have happened.
	 *
	 * @return void
	 */
	public static function redirect() {
		echo '<div class="wrap"><p><a class="button button-primary" href="' . \esc_url( self::url( 'wp-menu-direct' ) ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'See Ultimate Push Notifications Pro', 'ultimate-push-notifications' ) . '</a></p></div>';
	}

	/**
	 * The link for the Plugins screen's action row.
	 *
	 * @return string HTML
	 */
	public static function action_link() {
		return '<a href="' . \esc_url( self::url( 'plugins-list' ) ) . '" target="_blank" rel="noopener noreferrer" style="color:#1d8a1d;font-weight:600" aria-label="' . \esc_attr__( 'Upgrade to Ultimate Push Notifications Pro', 'ultimate-push-notifications' ) . '">' . \esc_html__( 'Upgrade to Pro', 'ultimate-push-notifications' ) . '</a>';
	}

}
