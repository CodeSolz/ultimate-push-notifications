<?php namespace UltimatePushNotifications\automation\triggers;

use UltimatePushNotifications\automation\Event;

/**
 * Core WordPress triggers.
 *
 * "Post published" is not here: AutoPush handles it with its own per-type
 * settings, and having it in two places would mean two notifications.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class WordPressTriggers {

	/**
	 * @return array[]
	 */
	public static function definitions() {
		return array(
			self::post_published(),
			self::post_updated(),
			self::user_register(),
			self::comment_posted(),
		);
	}

	/**
	 * Public post types, for the "of type" param.
	 *
	 * @return array slug => label
	 */
	public static function post_type_options() {
		$out = array();
		foreach ( (array) \get_post_types( array( 'public' => true ), 'objects' ) as $slug => $pt ) {
			if ( 'attachment' === $slug ) {
				continue;
			}
			$out[ $slug ] = isset( $pt->labels->singular_name ) ? (string) $pt->labels->singular_name : (string) $slug;
		}
		return $out;
	}

	/**
	 * @param string $trigger
	 * @param object $post
	 * @param string $key
	 * @return Event|null
	 */
	public static function post_event( $trigger, $post, $key ) {
		if ( ! $post || empty( $post->ID ) || 'publish' !== $post->post_status ) {
			return null;
		}
		$pt = \get_post_type_object( $post->post_type );
		if ( ! $pt || empty( $pt->public ) || 'attachment' === $post->post_type ) {
			return null;
		}
		$author = (int) $post->post_author;
		$event  = new Event( $trigger, array(
			'key'     => $key,
			'actor'   => Helpers::actor(),
			'params'  => array( 'post_type' => (string) $post->post_type ),
			'people'  => array( 'author' => $author > 0 ? array( $author ) : array() ),
			'context' => array( 'post' => $post, 'user' => Helpers::user( $author ) ),
			'url'     => (string) \get_permalink( $post ),
		) );
		$event->extra( 'author_name', Helpers::user_name( $author ) );
		$event->extra( 'post_type_name', isset( $pt->labels->singular_name ) ? (string) $pt->labels->singular_name : (string) $post->post_type );
		if ( \function_exists( 'get_the_post_thumbnail_url' ) ) {
			$event->image = (string) \get_the_post_thumbnail_url( $post, 'large' );
		}
		return $event;
	}

	/**
	 * Something goes live: a post, a page, a product — any public type.
	 */
	private static function post_published() {
		return array(
			'key'         => 'wp.post_published',
			'next'        => array( 'key' => 'automations.conditions', 'label' => \__( 'Only some posts', 'ultimate-push-notifications' ), 'text' => \__( 'send only for a category, a tag or an author, with conditions on this rule.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Post or product published', 'ultimate-push-notifications' ),
			'group'       => 'WordPress',
			'description' => \__( 'Content of a public type goes from draft, pending or scheduled to published.', 'ultimate-push-notifications' ),
			'hooks'       => array( 'transition_post_status' => 3 ),
			'params'      => array(
				'post_type' => array(
					'label'   => \__( 'Of type', 'ultimate-push-notifications' ),
					'options' => array( __CLASS__, 'post_type_options' ),
				),
			),
			'people'      => array( 'author' => \__( 'The author', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'author_name'    => \__( 'Author', 'ultimate-push-notifications' ),
				'post_type_name' => \__( 'Type', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'everyone',
				'title'    => '{post_title}',
				'body'     => '{post_excerpt}',
				'url'      => '{post_url}',
			),
			'build'       => function ( $args ) {
				list( $new, $old, $post ) = \array_pad( $args, 3, null );
				if ( 'publish' !== $new || 'publish' === $old || ! $post ) {
					return null;
				}
				return self::post_event( 'wp.post_published', $post, 'post_published:' . (int) $post->ID );
			},
		);
	}

	/**
	 * Published content edited. A save that changes nothing anyone reads
	 * (title, text, excerpt, price on a product) is ignored.
	 */
	private static function post_updated() {
		return array(
			'key'         => 'wp.post_updated',
			'next'        => array( 'key' => 'automations.digest', 'label' => \__( 'One digest instead of every edit', 'ultimate-push-notifications' ), 'text' => \__( 'bundle a busy day of updates into one notification per hour or per day.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Post or product updated', 'ultimate-push-notifications' ),
			'group'       => 'WordPress',
			'description' => \__( 'Already-published content is edited and saved.', 'ultimate-push-notifications' ),
			'hooks'       => array( 'post_updated' => 3 ),
			'params'      => array(
				'post_type' => array(
					'label'   => \__( 'Of type', 'ultimate-push-notifications' ),
					'options' => array( __CLASS__, 'post_type_options' ),
				),
			),
			'people'      => array( 'author' => \__( 'The author', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'author_name'    => \__( 'Author', 'ultimate-push-notifications' ),
				'post_type_name' => \__( 'Type', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'everyone',
				'title'    => \__( 'Updated: {post_title}', 'ultimate-push-notifications' ),
				'body'     => '{post_excerpt}',
				'url'      => '{post_url}',
			),
			'build'       => function ( $args ) {
				list( $post_id, $after, $before ) = \array_pad( $args, 3, null );
				if ( ! $after || ! $before || 'publish' !== $after->post_status || 'publish' !== $before->post_status ) {
					return null;
				}
				if ( \function_exists( 'wp_is_post_revision' ) && \wp_is_post_revision( $post_id ) ) {
					return null;
				}
				if ( $after->post_title === $before->post_title && $after->post_content === $before->post_content && $after->post_excerpt === $before->post_excerpt ) {
					return null; // a save that changed nothing readers see
				}
				return self::post_event( 'wp.post_updated', $after, 'post_updated:' . (int) $post_id . ':' . (string) $after->post_modified_gmt );
			},
		);
	}

	/**
	 * A new account. The old integration targeted the new user themself, who	 * has no device yet, so it could never fire; the audience people want is
	 * the administrators.
	 */
	private static function user_register() {
		return array(
			'key'         => 'wp.user_register',
			'label'       => \__( 'New user registers', 'ultimate-push-notifications' ),
			'group'       => 'WordPress',
			'description' => \__( 'Someone creates an account on the site.', 'ultimate-push-notifications' ),
			'hooks'       => array( 'user_register' => 1 ),
			'params'      => array(
				'role' => array(
					'label'   => \__( 'With role', 'ultimate-push-notifications' ),
					'options' => function () { return \wp_roles()->get_names(); },
				),
			),
			'people'      => array(
				'new_user' => \__( 'The new user', 'ultimate-push-notifications' ),
			),
			'tags'        => array(
				'user_name'  => \__( 'Display name', 'ultimate-push-notifications' ),
				'user_login' => \__( 'Username', 'ultimate-push-notifications' ),
				'user_email' => \__( 'Email', 'ultimate-push-notifications' ),
				'user_role'  => \__( 'Role', 'ultimate-push-notifications' ),
				'user_url'   => \__( 'Profile link (admin)', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'roles',
				'roles'    => array( 'administrator' ),
				'title'    => \__( 'New user: {user_name}', 'ultimate-push-notifications' ),
				'body'     => \__( '{user_email} just registered as {user_role}.', 'ultimate-push-notifications' ),
				'url'      => '{user_url}',
			),
			'build'       => function ( $args ) {
				$user = Helpers::user( isset( $args[0] ) ? $args[0] : 0 );
				if ( ! $user ) {
					return null;
				}
				$role  = ! empty( $user->roles ) ? (string) \reset( $user->roles ) : '';
				$names = \wp_roles()->get_names();

				$event = new Event( 'wp.user_register', array(
					'key'     => 'user_register:' . $user->ID,
					'actor'   => Helpers::actor() === (int) $user->ID ? 0 : Helpers::actor(),
					'params'  => array( 'role' => $role ),
					'people'  => array( 'new_user' => array( (int) $user->ID ) ),
					'context' => array( 'user' => $user ),
					'url'     => \admin_url( 'user-edit.php?user_id=' . (int) $user->ID ),
				) );
				$event->extra( 'user_name', $user->display_name );
				$event->extra( 'user_login', $user->user_login );
				$event->extra( 'user_email', $user->user_email );
				$event->extra( 'user_role', isset( $names[ $role ] ) ? $names[ $role ] : $role );
				$event->extra( 'user_url', $event->url );
				return $event;
			},
		);
	}

	/**
	 * An approved comment. Pending comments are skipped: the author would be
	 * told about spam.
	 */
	private static function comment_posted() {
		return array(
			'key'         => 'wp.comment_posted',
			'label'       => \__( 'Comment posted', 'ultimate-push-notifications' ),
			'group'       => 'WordPress',
			'description' => \__( 'An approved comment is added to a post.', 'ultimate-push-notifications' ),
			'hooks'       => array( 'comment_post' => 3, 'comment_unapproved_to_approved' => 1 ),
			'params'      => array(
				'post_type' => array(
					'label'   => \__( 'On post type', 'ultimate-push-notifications' ),
					'options' => function () {
						$out = array();
						foreach ( \get_post_types( array( 'public' => true ), 'objects' ) as $t ) {
							$out[ $t->name ] = $t->labels->singular_name;
						}
						return $out;
					},
				),
			),
			'people'      => array(
				'post_author' => \__( 'The post\'s author', 'ultimate-push-notifications' ),
			),
			'tags'        => array(
				'comment_author'  => \__( 'Commenter name', 'ultimate-push-notifications' ),
				'comment_excerpt' => \__( 'Comment excerpt', 'ultimate-push-notifications' ),
				'comment_url'     => \__( 'Comment link', 'ultimate-push-notifications' ),
				'post_title'      => \__( 'Post title', 'ultimate-push-notifications' ),
				'post_url'        => \__( 'Post link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'post_author',
				'title'    => \__( '{comment_author} commented on {post_title}', 'ultimate-push-notifications' ),
				'body'     => '{comment_excerpt}',
				'url'      => '{comment_url}',
			),
			'build'       => function ( $args ) {
				// comment_post passes (id, approved, data); comment_unapproved_to_approved passes the comment object.
				$first = isset( $args[0] ) ? $args[0] : null;
				if ( \is_object( $first ) ) {
					$comment = $first;
				} else {
					if ( ! isset( $args[1] ) || 1 !== (int) $args[1] ) {
						return null;
					}
					$comment = \get_comment( (int) $first );
				}
				if ( ! $comment || ( isset( $comment->comment_type ) && ! \in_array( $comment->comment_type, array( '', 'comment' ), true ) ) ) {
					return null;
				}
				$post = \get_post( (int) $comment->comment_post_ID );
				if ( ! $post ) {
					return null;
				}

				$event = new Event( 'wp.comment_posted', array(
					'key'     => 'comment:' . (int) $comment->comment_ID,
					'actor'   => (int) $comment->user_id,
					'params'  => array( 'post_type' => $post->post_type ),
					'people'  => array( 'post_author' => array( (int) $post->post_author ) ),
					'context' => array( 'post' => $post, 'user' => Helpers::user( $comment->user_id ) ),
					'url'     => (string) \get_comment_link( $comment ),
				) );
				$event->extra( 'comment_author', $comment->comment_author );
				$event->extra( 'comment_excerpt', Helpers::excerpt( $comment->comment_content, 20 ) );
				$event->extra( 'comment_url', $event->url );
				return $event;
			},
		);
	}

}
