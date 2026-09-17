<?php namespace UltimatePushNotifications\automation\triggers;

use UltimatePushNotifications\automation\Event;

/**
 * BuddyPress triggers.
 *
 * Activity events fan out to the people who would want to know — friends,
 * group members, the activity's author — never to the member who just
 * performed the action, which is what the old integration did.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class BuddyPressTriggers {

	/**
	 * @return array[]
	 */
	public static function definitions() {
		return array(
			self::friend_request(),
			self::friend_accepted(),
			self::message_sent(),
			self::activity_update(),
			self::activity_comment(),
			self::group_update(),
			self::group_joined(),
			self::new_follower(),
			self::followed_member_posted(),
		);
	}

	/**
	 * @return bool
	 */
	public static function available() {
		return \function_exists( 'buddypress' );
	}

	/**
	 * @param int $user_id
	 * @return int[]
	 */
	private static function friends_of( $user_id ) {
		if ( ! \function_exists( 'friends_get_friend_user_ids' ) ) {
			return array();
		}
		return \array_map( 'intval', (array) \friends_get_friend_user_ids( (int) $user_id ) );
	}

	/**
	 * @param int $group_id
	 * @return int[]
	 */
	private static function members_of( $group_id ) {
		if ( ! \function_exists( 'groups_get_group_members' ) ) {
			return array();
		}
		$r = \groups_get_group_members( array( 'group_id' => (int) $group_id, 'per_page' => 0, 'exclude_admins_mods' => false ) );
		$ids = array();
		foreach ( isset( $r['members'] ) ? (array) $r['members'] : array() as $m ) {
			$ids[] = (int) ( \is_object( $m ) ? ( isset( $m->ID ) ? $m->ID : $m->user_id ) : $m );
		}
		return $ids;
	}

	/**
	 * @param int $group_id
	 * @return int[] admins and moderators
	 */
	private static function leaders_of( $group_id ) {
		$ids = array();
		foreach ( array( 'groups_get_group_admins', 'groups_get_group_mods' ) as $fn ) {
			if ( \function_exists( $fn ) ) {
				foreach ( (array) \call_user_func( $fn, (int) $group_id ) as $u ) {
					$ids[] = (int) ( \is_object( $u ) ? $u->user_id : $u );
				}
			}
		}
		return $ids;
	}

	/**
	 * @param int $group_id
	 * @return array{name:string,url:string}
	 */
	private static function group_info( $group_id ) {
		$group = \function_exists( 'groups_get_group' ) ? \groups_get_group( (int) $group_id ) : null;
		return array(
			'name' => $group && isset( $group->name ) ? (string) $group->name : '',
			'url'  => $group && \function_exists( 'bp_get_group_permalink' ) ? (string) \bp_get_group_permalink( $group ) : '',
		);
	}

	/**
	 * @param int $activity_id
	 * @return string
	 */
	private static function activity_url( $activity_id ) {
		if ( ! \function_exists( 'bp_activity_get_permalink' ) ) {
			return '';
		}
		return (string) \bp_activity_get_permalink( (int) $activity_id );
	}

	/**
	 * Following exists in BuddyBoss Platform and in the BuddyPress Follow
	 * plugin; core BuddyPress has friends only.
	 *
	 * @return bool
	 */
	public static function follow_available() {
		return self::available() && ( \function_exists( 'bp_follow_get_followers' ) || \function_exists( 'bp_get_followers' ) );
	}

	/**
	 * Who follows a member.
	 *
	 * @param int $user_id
	 * @return int[]
	 */
	public static function followers( $user_id ) {
		$ids = array();
		if ( \function_exists( 'bp_follow_get_followers' ) ) {
			$ids = \bp_follow_get_followers( array( 'user_id' => (int) $user_id ) );
		} elseif ( \function_exists( 'bp_get_followers' ) ) {
			$ids = \bp_get_followers( array( 'user_id' => (int) $user_id ) );
		}
		return \array_values( \array_unique( \array_filter( \array_map( 'intval', (array) $ids ) ) ) );
	}

	/**
	 * Someone starts following a member. Both follow implementations pass a
	 * follow object with leader_id (the one followed) and follower_id.
	 */
	private static function new_follower() {
		return array(
			'key'         => 'bp.new_follower',
			'next'        => array( 'key' => 'cadence', 'label' => \__( 'Caps for popular members', 'ultimate-push-notifications' ), 'text' => \__( 'a member who gains fifty followers in an hour should get one notification, not fifty — frequency caps and quiet hours.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'New follower', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'description' => \__( 'A member starts following another (BuddyBoss, or the BuddyPress Follow plugin).', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'follow_available' ),
			'hooks'       => array( 'bp_follow_start_following' => 1, 'bp_start_following' => 1 ),
			'people'      => array( 'leader' => \__( 'The member who gained a follower', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'follower_name' => \__( 'Who followed', 'ultimate-push-notifications' ),
				'follower_url'  => \__( 'Their profile', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'leader',
				'title'    => \__( '{follower_name} is now following you', 'ultimate-push-notifications' ),
				'body'     => \__( 'Tap to see their profile.', 'ultimate-push-notifications' ),
				'url'      => '{follower_url}',
			),
			'build'       => function ( $args ) {
				$follow   = isset( $args[0] ) ? $args[0] : null;
				$leader   = \is_object( $follow ) && isset( $follow->leader_id ) ? (int) $follow->leader_id : 0;
				$follower = \is_object( $follow ) && isset( $follow->follower_id ) ? (int) $follow->follower_id : 0;
				if ( $leader <= 0 || $follower <= 0 ) {
					return null;
				}
				$event = new Event( 'bp.new_follower', array(
					'key'     => 'follow:' . $leader . ':' . $follower,
					'actor'   => $follower,
					'people'  => array( 'leader' => array( $leader ) ),
					'context' => array( 'user' => Helpers::user( $follower ) ),
					'url'     => Helpers::bp_user_url( $follower ),
				) );
				$event->extra( 'follower_name', Helpers::user_name( $follower ) );
				$event->extra( 'follower_url', $event->url );
				return $event;
			},
		);
	}

	/**
	 * A member you follow publishes something.
	 */
	private static function followed_member_posted() {
		return array(
			'key'         => 'bp.followed_member_posted',
			'next'        => array( 'key' => 'automations.digest', 'label' => \__( 'A digest for prolific authors', 'ultimate-push-notifications' ), 'text' => \__( 'followers of someone who posts ten times a day get one round-up instead of ten pings.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Someone you follow publishes a post', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'description' => \__( 'A member publishes content of a public type; their followers hear about it.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'follow_available' ),
			'hooks'       => array( 'transition_post_status' => 3 ),
			'params'      => array(
				'post_type' => array(
					'label'   => \__( 'Of type', 'ultimate-push-notifications' ),
					'options' => array( '\UltimatePushNotifications\automation\triggers\WordPressTriggers', 'post_type_options' ),
				),
			),
			'people'      => array( 'followers' => \__( 'The author\'s followers', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'author_name' => \__( 'Author', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'followers',
				'title'    => \__( '{author_name} published: {post_title}', 'ultimate-push-notifications' ),
				'body'     => '{post_excerpt}',
				'url'      => '{post_url}',
			),
			'build'       => function ( $args ) {
				list( $new, $old, $post ) = \array_pad( $args, 3, null );
				if ( 'publish' !== $new || 'publish' === $old || ! $post || empty( $post->post_author ) ) {
					return null;
				}
				$followers = self::followers( (int) $post->post_author );
				if ( ! $followers ) {
					return null;
				}
				$event = WordPressTriggers::post_event( 'bp.followed_member_posted', $post, 'followed_post:' . (int) $post->ID );
				if ( $event ) {
					$event->people = array( 'followers' => $followers, 'author' => array( (int) $post->post_author ) );
				}
				return $event;
			},
		);
	}

	private static function friend_request() {
		return array(
			'key'         => 'bp.friend_request',
			'label'       => \__( 'Friend request received', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'friends_friendship_requested' => 4 ),
			'people'      => array( 'recipient' => \__( 'The member who was asked', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'initiator_name' => \__( 'Who asked', 'ultimate-push-notifications' ),
				'requests_url'   => \__( 'Friend requests page', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'recipient',
				'title'    => \__( '{initiator_name} wants to be friends', 'ultimate-push-notifications' ),
				'body'     => \__( 'Tap to accept or ignore.', 'ultimate-push-notifications' ),
				'url'      => '{requests_url}',
			),
			'build'       => function ( $args ) {
				list( $friendship_id, $initiator, $friend ) = \array_pad( $args, 3, 0 );
				if ( (int) $friend <= 0 ) {
					return null;
				}
				$event = new Event( 'bp.friend_request', array(
					'key'     => 'friend_request:' . (int) $friendship_id,
					'actor'   => (int) $initiator,
					'people'  => array( 'recipient' => array( (int) $friend ) ),
					'context' => array( 'user' => Helpers::user( $initiator ) ),
					'url'     => \trailingslashit( Helpers::bp_user_url( $friend ) ) . 'friends/requests/',
				) );
				$event->extra( 'initiator_name', Helpers::user_name( $initiator ) );
				$event->extra( 'requests_url', $event->url );
				return $event;
			},
		);
	}

	private static function friend_accepted() {
		return array(
			'key'         => 'bp.friend_accepted',
			'label'       => \__( 'Friend request accepted', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'friends_friendship_accepted' => 4 ),
			'people'      => array( 'recipient' => \__( 'The member who asked', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'friend_name' => \__( 'Who accepted', 'ultimate-push-notifications' ),
				'profile_url' => \__( 'Their profile', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'recipient',
				'title'    => \__( '{friend_name} accepted your friend request', 'ultimate-push-notifications' ),
				'body'     => '',
				'url'      => '{profile_url}',
			),
			'build'       => function ( $args ) {
				list( $friendship_id, $initiator, $friend ) = \array_pad( $args, 3, 0 );
				if ( (int) $initiator <= 0 ) {
					return null;
				}
				$event = new Event( 'bp.friend_accepted', array(
					'key'     => 'friend_accepted:' . (int) $friendship_id,
					'actor'   => (int) $friend,
					'people'  => array( 'recipient' => array( (int) $initiator ) ),
					'context' => array( 'user' => Helpers::user( $friend ) ),
					'url'     => Helpers::bp_user_url( $friend ),
				) );
				$event->extra( 'friend_name', Helpers::user_name( $friend ) );
				$event->extra( 'profile_url', $event->url );
				return $event;
			},
		);
	}

	private static function message_sent() {
		return array(
			'key'         => 'bp.message_sent',
			'label'       => \__( 'Private message received', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'messages_message_sent' => 1 ),
			'people'      => array( 'recipients' => \__( 'The message recipients', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'sender_name'     => \__( 'Sender', 'ultimate-push-notifications' ),
				'message_subject' => \__( 'Subject', 'ultimate-push-notifications' ),
				'message_excerpt' => \__( 'Message excerpt', 'ultimate-push-notifications' ),
				'thread_url'      => \__( 'Conversation link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'recipients',
				'title'    => \__( 'New message from {sender_name}', 'ultimate-push-notifications' ),
				'body'     => '{message_excerpt}',
				'url'      => '{thread_url}',
			),
			'build'       => function ( $args ) {
				$message = isset( $args[0] ) && \is_object( $args[0] ) ? $args[0] : null;
				if ( ! $message || empty( $message->sender_id ) ) {
					return null;
				}
				$recipients = array();
				foreach ( isset( $message->recipients ) ? (array) $message->recipients : array() as $r ) {
					$id = (int) ( \is_object( $r ) ? $r->user_id : $r );
					if ( $id !== (int) $message->sender_id ) {
						$recipients[] = $id;
					}
				}
				$thread = isset( $message->thread_id ) ? (int) $message->thread_id : 0;
				$first  = $recipients ? $recipients[0] : 0;

				$event = new Event( 'bp.message_sent', array(
					'key'     => 'message:' . ( isset( $message->id ) ? (int) $message->id : \uniqid() ),
					'actor'   => (int) $message->sender_id,
					'people'  => array( 'recipients' => $recipients ),
					'context' => array( 'user' => Helpers::user( $message->sender_id ) ),
					'url'     => $first ? \trailingslashit( Helpers::bp_user_url( $first ) ) . 'messages/view/' . $thread . '/' : '',
				) );
				$event->extra( 'sender_name', Helpers::user_name( $message->sender_id ) );
				$event->extra( 'message_subject', Helpers::excerpt( isset( $message->subject ) ? $message->subject : '', 12 ) );
				$event->extra( 'message_excerpt', Helpers::excerpt( isset( $message->message ) ? $message->message : '', 20 ) );
				$event->extra( 'thread_url', $event->url );
				return $event;
			},
		);
	}

	private static function activity_update() {
		return array(
			'key'         => 'bp.activity_update',
			'label'       => \__( 'Member posts an update', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'bp_activity_posted_update' => 3 ),
			'people'      => array( 'friends' => \__( 'The member\'s friends', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'member_name'      => \__( 'Who posted', 'ultimate-push-notifications' ),
				'activity_excerpt' => \__( 'Update excerpt', 'ultimate-push-notifications' ),
				'activity_url'     => \__( 'Update link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'friends',
				'title'    => \__( '{member_name} posted an update', 'ultimate-push-notifications' ),
				'body'     => '{activity_excerpt}',
				'url'      => '{activity_url}',
			),
			'build'       => function ( $args ) {
				list( $content, $user_id, $activity_id ) = \array_pad( $args, 3, 0 );
				if ( (int) $user_id <= 0 ) {
					return null;
				}
				$event = new Event( 'bp.activity_update', array(
					'key'     => 'activity:' . (int) $activity_id,
					'actor'   => (int) $user_id,
					'people'  => array( 'friends' => self::friends_of( $user_id ) ),
					'context' => array( 'user' => Helpers::user( $user_id ) ),
					'url'     => self::activity_url( $activity_id ),
				) );
				$event->extra( 'member_name', Helpers::user_name( $user_id ) );
				$event->extra( 'activity_excerpt', Helpers::excerpt( $content, 20 ) );
				$event->extra( 'activity_url', $event->url );
				return $event;
			},
		);
	}

	private static function activity_comment() {
		return array(
			'key'         => 'bp.activity_comment',
			'label'       => \__( 'Reply to an update', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'bp_activity_comment_posted' => 3 ),
			'people'      => array( 'activity_author' => \__( 'The update\'s author', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'commenter_name'  => \__( 'Who replied', 'ultimate-push-notifications' ),
				'comment_excerpt' => \__( 'Reply excerpt', 'ultimate-push-notifications' ),
				'activity_url'    => \__( 'Update link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'activity_author',
				'title'    => \__( '{commenter_name} replied to your update', 'ultimate-push-notifications' ),
				'body'     => '{comment_excerpt}',
				'url'      => '{activity_url}',
			),
			'build'       => function ( $args ) {
				list( $comment_id, $params, $activity ) = \array_pad( $args, 3, null );
				$commenter = \is_array( $params ) && isset( $params['user_id'] ) ? (int) $params['user_id'] : Helpers::actor();
				$author    = \is_object( $activity ) && isset( $activity->user_id ) ? (int) $activity->user_id : 0;
				if ( $author <= 0 ) {
					return null;
				}
				$root  = \is_object( $activity ) && isset( $activity->id ) ? (int) $activity->id : 0;
				$event = new Event( 'bp.activity_comment', array(
					'key'     => 'activity_comment:' . (int) $comment_id,
					'actor'   => $commenter,
					'people'  => array( 'activity_author' => array( $author ) ),
					'context' => array( 'user' => Helpers::user( $commenter ) ),
					'url'     => self::activity_url( $root ),
				) );
				$event->extra( 'commenter_name', Helpers::user_name( $commenter ) );
				$event->extra( 'comment_excerpt', Helpers::excerpt( \is_array( $params ) && isset( $params['content'] ) ? $params['content'] : '', 20 ) );
				$event->extra( 'activity_url', $event->url );
				return $event;
			},
		);
	}

	private static function group_update() {
		return array(
			'key'         => 'bp.group_update',
			'label'       => \__( 'Update posted in a group', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'bp_groups_posted_update' => 4 ),
			'people'      => array( 'group_members' => \__( 'The group\'s members', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'group_name'       => \__( 'Group', 'ultimate-push-notifications' ),
				'member_name'      => \__( 'Who posted', 'ultimate-push-notifications' ),
				'activity_excerpt' => \__( 'Update excerpt', 'ultimate-push-notifications' ),
				'activity_url'     => \__( 'Update link', 'ultimate-push-notifications' ),
				'group_url'        => \__( 'Group link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'group_members',
				'title'    => \__( '{member_name} in {group_name}', 'ultimate-push-notifications' ),
				'body'     => '{activity_excerpt}',
				'url'      => '{activity_url}',
			),
			'build'       => function ( $args ) {
				list( $content, $user_id, $group_id, $activity_id ) = \array_pad( $args, 4, 0 );
				if ( (int) $group_id <= 0 ) {
					return null;
				}
				$group = self::group_info( $group_id );
				$event = new Event( 'bp.group_update', array(
					'key'     => 'group_activity:' . (int) $activity_id,
					'actor'   => (int) $user_id,
					'people'  => array( 'group_members' => self::members_of( $group_id ) ),
					'context' => array( 'user' => Helpers::user( $user_id ) ),
					'url'     => self::activity_url( $activity_id ),
				) );
				$event->extra( 'group_name', $group['name'] );
				$event->extra( 'group_url', $group['url'] );
				$event->extra( 'member_name', Helpers::user_name( $user_id ) );
				$event->extra( 'activity_excerpt', Helpers::excerpt( $content, 20 ) );
				$event->extra( 'activity_url', $event->url );
				return $event;
			},
		);
	}

	private static function group_joined() {
		return array(
			'key'         => 'bp.group_joined',
			'label'       => \__( 'Member joins a group', 'ultimate-push-notifications' ),
			'group'       => 'BuddyPress',
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'groups_join_group' => 2 ),
			'people'      => array(
				'group_leaders' => \__( 'The group\'s admins and moderators', 'ultimate-push-notifications' ),
				'new_member'    => \__( 'The new member', 'ultimate-push-notifications' ),
			),
			'tags'        => array(
				'group_name'  => \__( 'Group', 'ultimate-push-notifications' ),
				'member_name' => \__( 'Who joined', 'ultimate-push-notifications' ),
				'group_url'   => \__( 'Group link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'group_leaders',
				'title'    => \__( '{member_name} joined {group_name}', 'ultimate-push-notifications' ),
				'body'     => '',
				'url'      => '{group_url}',
			),
			'build'       => function ( $args ) {
				list( $group_id, $user_id ) = \array_pad( $args, 2, 0 );
				if ( (int) $group_id <= 0 || (int) $user_id <= 0 ) {
					return null;
				}
				$group = self::group_info( $group_id );
				$event = new Event( 'bp.group_joined', array(
					'key'     => 'group_join:' . (int) $group_id . ':' . (int) $user_id,
					'actor'   => (int) $user_id,
					'people'  => array( 'group_leaders' => self::leaders_of( $group_id ), 'new_member' => array( (int) $user_id ) ),
					'context' => array( 'user' => Helpers::user( $user_id ) ),
					'url'     => $group['url'],
				) );
				$event->extra( 'group_name', $group['name'] );
				$event->extra( 'group_url', $group['url'] );
				$event->extra( 'member_name', Helpers::user_name( $user_id ) );
				return $event;
			},
		);
	}

}
