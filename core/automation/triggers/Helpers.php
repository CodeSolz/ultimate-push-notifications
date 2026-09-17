<?php namespace UltimatePushNotifications\automation\triggers;

/**
 * Small shared formatting for trigger builders.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Helpers {

	/**
	 * Plain-text excerpt fit for a notification body.
	 *
	 * @param string $text
	 * @param int    $words
	 * @return string
	 */
	public static function excerpt( $text, $words = 20 ) {
		$text = \wp_strip_all_tags( \strip_shortcodes( (string) $text ) );
		$text = \trim( \preg_replace( '/\s+/u', ' ', $text ) );
		return \wp_trim_words( $text, $words, '…' );
	}

	/**
	 * A user's display name, however the site prefers to show it.
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function user_name( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return '';
		}
		if ( \function_exists( 'bp_core_get_user_displayname' ) ) {
			$name = \bp_core_get_user_displayname( $user_id );
			if ( '' !== (string) $name ) {
				return (string) $name;
			}
		}
		$user = \get_userdata( $user_id );
		return $user ? (string) $user->display_name : '';
	}

	/**
	 * @param int $user_id
	 * @return \WP_User|null
	 */
	public static function user( $user_id ) {
		$user = (int) $user_id > 0 ? \get_userdata( (int) $user_id ) : false;
		return $user ? $user : null;
	}

	/**
	 * An amount with its currency, as plain text.
	 *
	 * @param float|string $amount
	 * @param string       $currency
	 * @return string
	 */
	public static function money( $amount, $currency = '' ) {
		if ( \function_exists( 'wc_price' ) ) {
			$args = '' !== $currency ? array( 'currency' => $currency ) : array();
			return \html_entity_decode( \wp_strip_all_tags( \wc_price( $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
		}
		return \trim( $amount . ' ' . $currency );
	}

	/**
	 * The current user, when the event was caused by someone logged in.
	 *
	 * @return int
	 */
	public static function actor() {
		return \function_exists( 'get_current_user_id' ) ? (int) \get_current_user_id() : 0;
	}

	/**
	 * A BuddyPress member's profile URL across BP versions.
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function bp_user_url( $user_id ) {
		if ( \function_exists( 'bp_members_get_user_url' ) ) {
			return (string) \bp_members_get_user_url( (int) $user_id );
		}
		if ( \function_exists( 'bp_core_get_user_domain' ) ) {
			return (string) \bp_core_get_user_domain( (int) $user_id );
		}
		return '';
	}

}
