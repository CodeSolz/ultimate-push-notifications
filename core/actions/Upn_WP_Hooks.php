<?php namespace UltimatePushNotifications\actions;

/**
 * Class: WordPress Default Hooks
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\pro\Upgrade;
use UltimatePushNotifications\admin\options\pages\AboutUsPage;

class Upn_WP_Hooks {

	function __construct() {
		/*** add settings link */
		add_filter( 'plugin_action_links_' . CS_UPN_PLUGIN_IDENTIFIER, array( $this, 'upn_action_links' ) );

		/*** add docs link */
		add_filter( 'plugin_row_meta', array( $this, 'upn_plugin_row_meta' ), 10, 2 );

		/*** the Upgrade to Pro menu entry, then About Us, last */
		Upgrade::boot();
		AboutUsPage::boot();
	}

	/**
	 * Add settings links
	 *
	 * @param array $links
	 * @return array
	 */
	public static function upn_action_links( $links ) {
		$custom_links = array(
			'compose'    => '<a href="' . Util::cs_generate_admin_url( 'cs-upn-compose' ) . '">' . esc_html__( 'Compose', 'ultimate-push-notifications' ) . '</a>',
			'app_config' => '<a href="' . Util::cs_generate_admin_url( 'cs-upn-app-configuration' ) . '" aria-label="' . esc_attr__( 'Push notification settings', 'ultimate-push-notifications' ) . '">' . esc_html__( 'Settings', 'ultimate-push-notifications' ) . '</a>',
		);

		// First in the row, as long as Pro is not there.
		$pro_links = Upgrade::pro_active() ? array() : array( 'upn_go_pro' => Upgrade::action_link() );

		return array_merge( $pro_links, $custom_links, (array) $links );
	}

	/**
	 * Plugins Row
	 *
	 * @param array  $links
	 * @param string $file
	 * @return array
	 */
	public function upn_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( CS_UPN_PLUGIN_IDENTIFIER ) !== $file ) {
			return $links;
		}

		$row_meta = array(
			'docs'    => '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://docs.codesolz.net/ultimate-push-notifications/' ) . '" aria-label="' . esc_attr__( 'Documentation', 'ultimate-push-notifications' ) . '">' . esc_html__( 'Docs', 'ultimate-push-notifications' ) . '</a>',
			'videos'  => '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://www.youtube.com/watch?v=TARCZGGlG5k&list=PLxLVEan0phTsg6006fmSx2QHzn28QPkJB' ) . '" aria-label="' . esc_attr__( 'Video Tutorials', 'ultimate-push-notifications' ) . '">' . esc_html__( 'Video Tutorials', 'ultimate-push-notifications' ) . '</a>',
			'support' => '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://codesolz.net/forum' ) . '" aria-label="' . esc_attr__( 'Community support', 'ultimate-push-notifications' ) . '">' . esc_html__( 'Community support', 'ultimate-push-notifications' ) . '</a>',
		);
		if ( ! Upgrade::pro_active() ) {
			$row_meta['pro'] = '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( Upgrade::url( 'plugins-row-meta', 'pro-features' ) ) . '" aria-label="' . esc_attr__( 'What Ultimate Push Notifications Pro adds', 'ultimate-push-notifications' ) . '"><strong>' . esc_html__( 'Pro features', 'ultimate-push-notifications' ) . '</strong></a>';
		}

		/**
		 * The links under the plugin's description on the Plugins screen.
		 *
		 * @param array $row_meta
		 */
		$row_meta = apply_filters( 'upn_row_meta', $row_meta );

		return array_merge( $links, $row_meta );
	}

}
