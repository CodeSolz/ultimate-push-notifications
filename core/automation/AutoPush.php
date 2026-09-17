<?php namespace UltimatePushNotifications\automation;

use UltimatePushNotifications\messaging\Composer;

/**
 * Send a notification when content is published.
 *
 * The one automation every publisher wants and the one the plugin never had.
 * Free ships a single site-wide rule: which post types, what the title and
 * body say (merge tags), who gets it. Per-type rules, category filters and
 * scheduling are the natural next-stage problem and belong to Pro.
 *
 * Fires on transition_post_status rather than publish_post so that scheduled
 * posts, REST publishes and every custom post type all go through one path.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class AutoPush {

	const OPTION = 'upn_autopush';

	/** Post meta that records a push was sent, so an edit cannot resend. */
	const META_SENT = '_upn_autopushed';

	/**
	 * Defaults for a fresh install — off, but ready to switch on.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'            => false,
			'post_types'         => array( 'post' ),
			'title'              => '{post_title}',
			'body'               => '{post_excerpt}',
			'use_featured_image' => true,
			'audience'           => array( 'who' => 'any' ),
			'urgency'            => 'normal',
			'ttl'                => 86400,
		);
	}

	/**
	 * Current settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = \get_option( self::OPTION );
		return self::sanitize( \is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Sanitise settings from a form or the option.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize( $input ) {
		$d = self::defaults();
		$input = \is_array( $input ) ? $input : array();

		$public_types = \array_keys( (array) \get_post_types( array( 'public' => true ) ) );
		$types        = isset( $input['post_types'] ) ? \array_map( 'sanitize_key', (array) $input['post_types'] ) : $d['post_types'];
		$types        = \array_values( \array_intersect( $types, $public_types ) );

		return array(
			'enabled'            => ! empty( $input['enabled'] ),
			'post_types'         => $types,
			'title'              => isset( $input['title'] ) ? \wp_strip_all_tags( \trim( (string) $input['title'] ) ) : $d['title'],
			'body'               => isset( $input['body'] ) ? \wp_strip_all_tags( \trim( (string) $input['body'] ) ) : $d['body'],
			'use_featured_image' => ! isset( $input['use_featured_image'] ) ? $d['use_featured_image'] : ! empty( $input['use_featured_image'] ),
			'audience'           => Composer::sanitize_audience( isset( $input['audience'] ) && \is_array( $input['audience'] ) ? $input['audience'] : $d['audience'] ),
			'urgency'            => isset( $input['urgency'] ) && \in_array( $input['urgency'], array( 'low', 'normal', 'high' ), true ) ? $input['urgency'] : 'normal',
			'ttl'                => isset( $input['ttl'] ) ? \max( 60, \min( 604800, (int) $input['ttl'] ) ) : $d['ttl'],
		);
	}

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function boot() {
		\add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
	}

	/**
	 * Decide whether this transition is a publish worth announcing.
	 *
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 * @return bool
	 */
	public static function should_send( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return false;
		}

		if ( ! \is_object( $post ) || empty( $post->ID ) || empty( $post->post_type ) ) {
			return false;
		}

		if ( \wp_is_post_revision( $post->ID ) || \wp_is_post_autosave( $post->ID ) ) {
			return false;
		}

		$settings = self::settings();

		if ( ! $settings['enabled'] || ! \in_array( $post->post_type, $settings['post_types'], true ) ) {
			return false;
		}

		// Password-protected or private content must not leak its title.
		if ( ! empty( $post->post_password ) ) {
			return false;
		}

		// Already announced once; an unpublish → republish cycle stays quiet.
		if ( \get_post_meta( $post->ID, self::META_SENT, true ) ) {
			return false;
		}

		/**
		 * Filter whether a publish should trigger a push.
		 *
		 * @param bool     $send
		 * @param \WP_Post $post
		 * @param string   $old_status
		 */
		return (bool) \apply_filters( 'upn_autopush_should_send', true, $post, $old_status );
	}

	/**
	 * transition_post_status callback.
	 *
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 * @return void
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( ! self::should_send( $new_status, $old_status, $post ) ) {
			return;
		}

		self::send_for_post( $post );
	}

	/**
	 * Build and send the notification for a post.
	 *
	 * @param \WP_Post $post
	 * @return array|\WP_Error Composer::send() result.
	 */
	public static function send_for_post( $post ) {
		$settings = self::settings();

		$fields = Composer::sanitize(
			array(
				'title'        => $settings['title'],
				'body'         => $settings['body'],
				'click_action' => (string) \get_permalink( $post ),
				'icon'         => self::site_icon_url(),
				'image'        => $settings['use_featured_image'] ? self::featured_image_url( $post ) : '',
				'tag'          => 'post-' . (int) $post->ID,
				'urgency'      => $settings['urgency'],
				'ttl'          => $settings['ttl'],
			)
		);

		if ( \is_wp_error( $fields ) ) {
			return $fields;
		}

		/**
		 * Filter the notification fields for an auto-push before merge tags render.
		 *
		 * @param array    $fields
		 * @param \WP_Post $post
		 */
		$fields = (array) \apply_filters( 'upn_autopush_fields', $fields, $post );

		$result = Composer::send( $fields, $settings['audience'], array( 'post' => $post ), 'autopush' );

		if ( ! \is_wp_error( $result ) ) {
			\update_post_meta( $post->ID, self::META_SENT, \time() );

			/**
			 * Fires after an auto-push has been queued or sent.
			 *
			 * @param \WP_Post $post
			 * @param array    $result
			 */
			\do_action( 'upn_autopush_sent', $post, $result );
		}

		return $result;
	}

	/**
	 * The site icon, if one is set, as the notification icon.
	 *
	 * @return string
	 */
	private static function site_icon_url() {
		$url = \function_exists( 'get_site_icon_url' ) ? \get_site_icon_url( 192 ) : '';
		return $url ? (string) $url : '';
	}

	/**
	 * The featured image, sized for a notification.
	 *
	 * @param \WP_Post $post
	 * @return string
	 */
	private static function featured_image_url( $post ) {
		if ( ! \function_exists( 'get_the_post_thumbnail_url' ) ) {
			return '';
		}
		$url = \get_the_post_thumbnail_url( $post, 'large' );
		return $url ? (string) $url : '';
	}

}
