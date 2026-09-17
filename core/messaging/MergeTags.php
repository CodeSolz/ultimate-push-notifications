<?php namespace UltimatePushNotifications\messaging;

/**
 * Merge tags: {site_name}, {post_title}, {user_name} …
 *
 * A registry, so that the tags a screen documents and the tags the sender
 * substitutes are the same list — the old code documented {product_price}
 * and implemented {price}, and nobody could tell without reading both files.
 *
 * Resolvers receive a context array (post, user, order, …) and return a
 * string. Unknown tags are left in place: a notification that says
 * "{ordr_id}" tells the author what they mistyped; one that silently drops
 * it does not.
 *
 * @package Messaging
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class MergeTags {

	/**
	 * tag => array( label, group, resolver )
	 *
	 * @var array|null
	 */
	private static $tags = null;

	/**
	 * Every registered tag.
	 *
	 * @return array<string, array{label:string, group:string, resolver:callable}>
	 */
	public static function all() {
		if ( null !== self::$tags ) {
			return self::$tags;
		}

		$tags = array();

		$site = \__( 'Site', 'ultimate-push-notifications' );
		$tags['site_name'] = array( 'label' => \__( 'Site name', 'ultimate-push-notifications' ), 'group' => $site, 'resolver' => function () { return \get_bloginfo( 'name' ); } );
		$tags['site_url']  = array( 'label' => \__( 'Site URL', 'ultimate-push-notifications' ), 'group' => $site, 'resolver' => function () { return \home_url( '/' ); } );
		$tags['date']      = array( 'label' => \__( 'Today\'s date', 'ultimate-push-notifications' ), 'group' => $site, 'resolver' => function () { return \date_i18n( \get_option( 'date_format' ) ); } );
		$tags['time']      = array( 'label' => \__( 'Current time', 'ultimate-push-notifications' ), 'group' => $site, 'resolver' => function () { return \date_i18n( \get_option( 'time_format' ) ); } );

		$post = \__( 'Post', 'ultimate-push-notifications' );
		$tags['post_title']   = array( 'label' => \__( 'Post title', 'ultimate-push-notifications' ), 'group' => $post, 'resolver' => function ( $c ) { return isset( $c['post'] ) ? \get_the_title( $c['post'] ) : ''; } );
		$tags['post_url']     = array( 'label' => \__( 'Post URL', 'ultimate-push-notifications' ), 'group' => $post, 'resolver' => function ( $c ) { return isset( $c['post'] ) ? (string) \get_permalink( $c['post'] ) : ''; } );
		$tags['post_excerpt'] = array( 'label' => \__( 'Post excerpt', 'ultimate-push-notifications' ), 'group' => $post, 'resolver' => function ( $c ) { return isset( $c['post'] ) ? self::excerpt( $c['post'] ) : ''; } );
		$tags['post_type']    = array( 'label' => \__( 'Post type', 'ultimate-push-notifications' ), 'group' => $post, 'resolver' => function ( $c ) { return isset( $c['post']->post_type ) ? (string) $c['post']->post_type : ''; } );
		$tags['author_name']  = array( 'label' => \__( 'Author name', 'ultimate-push-notifications' ), 'group' => $post, 'resolver' => function ( $c ) { return isset( $c['post']->post_author ) ? (string) \get_the_author_meta( 'display_name', $c['post']->post_author ) : ''; } );

		$user = \__( 'User', 'ultimate-push-notifications' );
		$tags['user_name']    = array( 'label' => \__( 'User display name', 'ultimate-push-notifications' ), 'group' => $user, 'resolver' => function ( $c ) { return isset( $c['user']->display_name ) ? (string) $c['user']->display_name : ''; } );
		$tags['user_login']   = array( 'label' => \__( 'User login', 'ultimate-push-notifications' ), 'group' => $user, 'resolver' => function ( $c ) { return isset( $c['user']->user_login ) ? (string) $c['user']->user_login : ''; } );
		$tags['user_email']   = array( 'label' => \__( 'User email', 'ultimate-push-notifications' ), 'group' => $user, 'resolver' => function ( $c ) { return isset( $c['user']->user_email ) ? (string) $c['user']->user_email : ''; } );
		$tags['first_name']   = array( 'label' => \__( 'First name', 'ultimate-push-notifications' ), 'group' => $user, 'resolver' => function ( $c ) { return isset( $c['user']->ID ) ? (string) \get_user_meta( $c['user']->ID, 'first_name', true ) : ''; } );

		/**
		 * Filter the merge tag registry.
		 *
		 * Each entry: tag => array( 'label' => string, 'group' => string,
		 * 'resolver' => callable( array $context ): string ).
		 *
		 * @param array $tags
		 */
		$tags = (array) \apply_filters( 'upn_merge_tags', $tags );

		self::$tags = array();
		foreach ( $tags as $tag => $def ) {
			if ( \is_array( $def ) && isset( $def['resolver'] ) && \is_callable( $def['resolver'] ) && \preg_match( '/^[a-z0-9_]+$/', (string) $tag ) ) {
				self::$tags[ $tag ] = array(
					'label'    => isset( $def['label'] ) ? (string) $def['label'] : $tag,
					'group'    => isset( $def['group'] ) ? (string) $def['group'] : '',
					'resolver' => $def['resolver'],
				);
			}
		}

		return self::$tags;
	}

	/**
	 * Substitute every {tag} in a string.
	 *
	 * @param string $text
	 * @param array  $context Whatever the event knows: post, user, order, product, extra (tag => value).
	 * @return string
	 */
	public static function render( $text, array $context = array() ) {
		$text = (string) $text;

		if ( false === \strpos( $text, '{' ) ) {
			return $text;
		}

		$tags = self::all();

		return \preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			function ( $m ) use ( $tags, $context ) {
				$tag = $m[1];

				// Ad-hoc values an event supplies without registering a tag.
				if ( isset( $context['extra'] ) && \is_array( $context['extra'] ) && \array_key_exists( $tag, $context['extra'] ) ) {
					return (string) $context['extra'][ $tag ];
				}

				if ( ! isset( $tags[ $tag ] ) ) {
					return $m[0];
				}

				try {
					return (string) \call_user_func( $tags[ $tag ]['resolver'], $context );
				} catch ( \Throwable $e ) {
					return '';
				}
			},
			$text
		);
	}

	/**
	 * Tags grouped for a reference panel.
	 *
	 * @return array<string, array<string,string>> group => (tag => label)
	 */
	public static function grouped() {
		$out = array();
		foreach ( self::all() as $tag => $def ) {
			$out[ $def['group'] ][ $tag ] = $def['label'];
		}
		return $out;
	}

	/**
	 * Reset the registry. Tests only.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$tags = null;
	}

	/**
	 * A short plain-text excerpt.
	 *
	 * @param \WP_Post|object $post
	 * @return string
	 */
	private static function excerpt( $post ) {
		$text = isset( $post->post_excerpt ) && '' !== $post->post_excerpt ? $post->post_excerpt : ( isset( $post->post_content ) ? $post->post_content : '' );
		$text = \wp_strip_all_tags( \strip_shortcodes( (string) $text ) );
		$text = \preg_replace( '/\s+/', ' ', $text );
		return \wp_html_excerpt( \trim( $text ), 120, '…' );
	}

}
