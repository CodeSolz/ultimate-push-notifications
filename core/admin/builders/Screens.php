<?php namespace UltimatePushNotifications\admin\builders;

/**
 * The admin screens as a few menu entries with tabs, instead of one menu
 * entry per screen.
 *
 * Every screen keeps its own page slug (admin.php?page=cs-upn-…), so links,
 * redirects and bookmarks keep working; what changes is what the menu shows.
 * A screen registers itself with the group it belongs to. On admin_menu
 * (late, so the Pro plugin has registered too) each group becomes one
 * submenu entry pointing at the first tab the current user may see; the
 * other tabs are registered under the same parent (so their page hooks
 * are the ordinary ones), then moved to a private parent slug no menu
 * shows, which $_wp_real_parent_file maps back to the real menu — the
 * mechanism core uses for its own unlisted screens. So they route, check
 * their capability, and keep the plugin's menu open and the group entry
 * lit while they are the page.
 * The panel heading draws the group's tabs, and the group entry stays
 * highlighted whichever tab is open.
 *
 * @package Builder
 * @since 1.6.2
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Screens {

	/** @var array<string,array> slug => title, menu, cap, callback, group, order */
	private static $pages = array();

	/** @var array<string,string> slug => hook suffix from add_submenu_page() */
	private static $hooks = array();

	/** @var array<string,string> group id => the slug its menu entry points at */
	private static $entry = array();

	/** @var array<string,true> slugs hidden from the tab strip for this user */
	private static $hidden = array();

	/** @var array<string,array> slug => a Pro screen advertised as a tab while Pro is not there to register it */
	private static $adverts = array();

	/** The parent hidden tabs are registered under; no menu lists it, core maps it to the real one. */
	const HIDDEN_PARENT = 'upn-tab';

	/** @var bool */
	private static $booted = false;

	/**
	 * The groups, in menu order.
	 *
	 * @return array<string,string> id => menu label
	 */
	public static function groups() {
		$groups = array(
			'compose'     => \__( 'Compose', 'ultimate-push-notifications' ),
			'automations' => \__( 'Automations', 'ultimate-push-notifications' ),
			'subscribers' => \__( 'Subscribers', 'ultimate-push-notifications' ),
			'optin'       => \__( 'Subscribe Prompt', 'ultimate-push-notifications' ),
			'health'      => \__( 'Health', 'ultimate-push-notifications' ),
			'settings'    => \__( 'Settings', 'ultimate-push-notifications' ),
		);
		/**
		 * The menu groups. Add one to give an extension its own entry.
		 *
		 * @param array $groups id => label
		 */
		return (array) \apply_filters( 'upn_admin_groups', $groups );
	}

	/**
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		\add_action( 'admin_menu', array( __CLASS__, 'emit' ), 50 );
		\add_filter( 'parent_file', array( __CLASS__, 'parent' ) );
		\add_filter( 'submenu_file', array( __CLASS__, 'highlight' ) );
	}

	/**
	 * Register a screen. Call on admin_menu before priority 50.
	 *
	 * @param string   $slug     Page slug (cs-upn-…).
	 * @param string   $label    Tab / menu label.
	 * @param string   $cap      Capability.
	 * @param callable $callback Renders the screen.
	 * @param string   $group    One of groups(); an unknown group gets its own menu entry.
	 * @param string   $title    Browser title; the label by default.
	 * @param int      $order    Position among the group's tabs.
	 * @return void
	 */
	public static function register( $slug, $label, $cap, $callback, $group, $title = '', $order = 10 ) {
		self::$pages[ (string) $slug ] = array(
			'slug'     => (string) $slug,
			'label'    => (string) $label,
			'title'    => '' !== (string) $title ? (string) $title : (string) $label,
			'cap'      => (string) $cap,
			'callback' => $callback,
			'group'    => (string) $group,
			'order'    => (int) $order,
		);
	}

	/**
	 * Advertise a Pro screen: a tab with a Pro badge whose page explains the
	 * feature. Used only when nothing registers the slug for real.
	 *
	 * @param string   $slug
	 * @param string   $label
	 * @param string   $group
	 * @param int      $order
	 * @param string   $title
	 * @param callable $callback Renders the explanation.
	 * @return void
	 */
	public static function advertise( $slug, $label, $group, $order, $title, $callback ) {
		self::$adverts[ (string) $slug ] = array(
			'slug'     => (string) $slug,
			'label'    => (string) $label,
			'title'    => '' !== (string) $title ? (string) $title : (string) $label,
			'cap'      => 'manage_options',
			'callback' => $callback,
			'group'    => (string) $group,
			'order'    => (int) $order,
			'pro'      => true,
		);
	}

	/**
	 * @param string $slug
	 * @return bool an advertised Pro screen, not a real one
	 */
	public static function is_advert( $slug ) {
		return isset( self::$pages[ $slug ]['pro'] ) && true === self::$pages[ $slug ]['pro'];
	}

	/**
	 * Keep a screen out of the tab strip (it stays routable).
	 *
	 * @param string $slug
	 * @return void
	 */
	public static function hide( $slug ) {
		self::$hidden[ (string) $slug ] = true;
	}

	/**
	 * @param string $slug
	 * @return array|null
	 */
	public static function page( $slug ) {
		return isset( self::$pages[ $slug ] ) ? self::$pages[ $slug ] : null;
	}

	/**
	 * @return array<string,array>
	 */
	public static function all() {
		return self::$pages;
	}

	/**
	 * The screens of a group, in tab order.
	 *
	 * @param string $group
	 * @return array[]
	 */
	public static function in_group( $group ) {
		$out = array();
		foreach ( self::$pages as $p ) {
			if ( $p['group'] === $group ) {
				$out[] = $p;
			}
		}
		\usort( $out, function ( $a, $b ) { return $a['order'] <=> $b['order']; } );
		return $out;
	}

	/**
	 * Build the menu: one entry per group, the rest routable but unlisted.
	 *
	 * @return void
	 */
	public static function emit() {
		if ( ! \defined( 'CS_UPN_PLUGIN_IDENTIFIER' ) ) {
			return;
		}
		global $submenu, $_wp_real_parent_file;
		$parent = CS_UPN_PLUGIN_IDENTIFIER;
		// The Pro screens nothing registered for real: tabs that explain them.
		foreach ( self::$adverts as $slug => $ad ) {
			if ( ! isset( self::$pages[ $slug ] ) ) {
				self::$pages[ $slug ] = $ad;
			}
		}
		$hidden = array();
		$groups = self::groups();
		// Screens whose group nobody declared get an entry of their own, after the known groups.
		foreach ( self::$pages as $p ) {
			if ( ! isset( $groups[ $p['group'] ] ) ) {
				$groups[ $p['group'] ] = $p['label'];
			}
		}
		foreach ( $groups as $gid => $label ) {
			$tabs = self::in_group( $gid );
			if ( ! $tabs ) {
				continue;
			}
			$entry = null;
			foreach ( $tabs as $t ) {
				if ( \current_user_can( $t['cap'] ) ) {
					$entry = $t;
					break;
				}
			}
			if ( null === $entry ) {
				$entry = $tabs[0]; // WordPress hides it for this user; the slug still routes for those who may.
			}
			self::$entry[ $gid ] = $entry['slug'];
			foreach ( $tabs as $t ) {
				$is_entry = $t['slug'] === $entry['slug'];
				$hook = \add_submenu_page( $parent, $t['title'], $is_entry ? $label : $t['label'], $t['cap'], $t['slug'], $t['callback'] );
				if ( \is_string( $hook ) ) {
					self::$hooks[ $t['slug'] ] = $hook;
				}
				if ( ! $is_entry ) {
					$hidden[] = $t['slug'];
				}
			}
		}
		/*
		 * Registration must see the real parent (that is what the page hook and
		 * add_submenu_page() itself are keyed by). Only now are the hidden tabs
		 * moved under the private parent, and the private parent mapped back, so
		 * get_admin_page_parent() resolves them to this menu on their own request.
		 */
		foreach ( $hidden as $slug ) {
			$item = \remove_submenu_page( $parent, $slug );
			if ( \is_array( $item ) ) {
				$submenu[ self::HIDDEN_PARENT ][] = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- unlisted, still registered
			}
		}
		\remove_submenu_page( $parent, $parent ); // the parent's own copy core adds with the first child
		$_wp_real_parent_file[ self::HIDDEN_PARENT ] = $parent; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the documented map for unlisted screens
	}

	/**
	 * Keep the plugin's menu open on a hidden tab.
	 *
	 * @param string $parent_file
	 * @return string
	 */
	public static function parent( $parent_file ) {
		return self::page( self::current() ) && \defined( 'CS_UPN_PLUGIN_IDENTIFIER' ) ? CS_UPN_PLUGIN_IDENTIFIER : $parent_file;
	}

	/**
	 * Keep the group's entry lit on any of its tabs.
	 *
	 * @param string|null $submenu_file
	 * @return string|null
	 */
	public static function highlight( $submenu_file ) {
		$p = self::page( self::current() );
		if ( $p && isset( self::$entry[ $p['group'] ] ) ) {
			return self::$entry[ $p['group'] ];
		}
		return $submenu_file;
	}

	/**
	 * @param string $slug
	 * @return string hook suffix, '' when unknown
	 */
	public static function hook( $slug ) {
		return isset( self::$hooks[ $slug ] ) ? self::$hooks[ $slug ] : '';
	}

	/**
	 * @return string the page slug of the current request
	 */
	public static function current() {
		return isset( $_GET['page'] ) ? \sanitize_key( \wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing, read only
	}

	/**
	 * The tab strip for the group a screen belongs to. Nothing for a group
	 * with a single tab the user may see.
	 *
	 * @param string $slug Current screen.
	 * @return string HTML
	 */
	public static function tabs_html( $slug ) {
		$p = self::page( $slug );
		if ( ! $p ) {
			return '';
		}
		$items = array();
		foreach ( self::in_group( $p['group'] ) as $t ) {
			if ( isset( self::$hidden[ $t['slug'] ] ) || ! \current_user_can( $t['cap'] ) ) {
				continue;
			}
			$pro     = ! empty( $t['pro'] ) ? ' <span class="upn-pro-badge">' . \esc_html__( 'Pro', 'ultimate-push-notifications' ) . '</span>' : '';
			$items[] = '<li class="' . ( $t['slug'] === $slug ? 'active' : '' ) . ( '' !== $pro ? ' is-pro' : '' ) . '"><a href="' . \esc_url( \admin_url( 'admin.php?page=' . $t['slug'] ) ) . '"' . ( $t['slug'] === $slug ? ' aria-current="page"' : '' ) . '>' . \esc_html( $t['label'] ) . $pro . '</a></li>';
		}
		if ( \count( $items ) < 2 ) {
			return '';
		}
		return '<ul class="upn-screen-tabs">' . \implode( '', $items ) . '</ul>';
	}

}
