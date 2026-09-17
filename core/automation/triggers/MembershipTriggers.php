<?php namespace UltimatePushNotifications\automation\triggers;

use UltimatePushNotifications\automation\Event;

/**
 * Membership and course plugins: the moments a member is most receptive —
 * joining, being approved, finishing a course — and the ones a site owner
 * must not miss — a membership ending.
 *
 * Each trigger appears only while its plugin is active. Supported: Paid
 * Memberships Pro, MemberPress, Restrict Content Pro, WooCommerce
 * Memberships, Ultimate Member, LearnDash. Audiences are the member (the
 * person the event is about) or, as with every trigger, roles, users,
 * members, visitors or everyone.
 *
 * @package UltimatePushNotifications
 * @since 1.6.2
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class MembershipTriggers {

	/**
	 * @return array[]
	 */
	public static function definitions() {
		return array(
			self::pmpro_level_changed(),
			self::mepr_transaction_completed(),
			self::rcp_activated(),
			self::rcp_expired(),
			self::wcm_status_changed(),
			self::um_approved(),
			self::ld_course_completed(),
		);
	}

	/**
	 * @param string $trigger
	 * @param string $key
	 * @param int    $user_id
	 * @param array  $params
	 * @param array  $extra   tag => value
	 * @param string $url
	 * @return Event|null
	 */
	private static function member_event( $trigger, $key, $user_id, array $params, array $extra, $url = '' ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return null;
		}
		$user  = Helpers::user( $user_id );
		$event = new Event( $trigger, array(
			'key'     => $key,
			'actor'   => Helpers::actor() === $user_id ? 0 : Helpers::actor(),
			'params'  => $params,
			'people'  => array( 'member' => array( $user_id ) ),
			'context' => array( 'user' => $user ),
			'url'     => '' !== $url ? $url : \home_url( '/' ),
		) );
		$event->extra( 'member_name', Helpers::user_name( $user_id ) );
		$event->extra( 'member_email', $user ? (string) $user->user_email : '' );
		foreach ( $extra as $k => $v ) {
			$event->extra( $k, (string) $v );
		}
		return $event;
	}

	/**
	 * @param array $tags
	 * @return array
	 */
	private static function tags( array $tags = array() ) {
		return $tags + array(
			'member_name'  => \__( 'Member', 'ultimate-push-notifications' ),
			'member_email' => \__( 'Member email', 'ultimate-push-notifications' ),
			'member_url'   => \__( 'Account page', 'ultimate-push-notifications' ),
		);
	}

	/* ---- Paid Memberships Pro -------------------------------------------------------- */

	public static function pmpro_available() {
		return \function_exists( 'pmpro_getLevel' );
	}

	public static function pmpro_levels() {
		$out = array( '0' => \__( 'Cancelled (no level)', 'ultimate-push-notifications' ) );
		if ( \function_exists( 'pmpro_getAllLevels' ) ) {
			foreach ( (array) \pmpro_getAllLevels( true, true ) as $level ) {
				if ( isset( $level->id, $level->name ) ) {
					$out[ (string) (int) $level->id ] = (string) $level->name;
				}
			}
		}
		return $out;
	}

	private static function pmpro_level_changed() {
		return array(
			'key'         => 'member.pmpro_level_changed',
			'next'        => array( 'key' => 'carriers', 'label' => \__( 'Reach them before they subscribe', 'ultimate-push-notifications' ), 'text' => \__( 'a new member rarely has push yet — send the same message by email, Slack or Telegram when push has nobody to reach.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Membership level changes (Paid Memberships Pro)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'A member joins a level, moves to another, or is cancelled.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'pmpro_available' ),
			'hooks'       => array( 'pmpro_after_change_membership_level' => 3 ),
			'params'      => array(
				'level' => array( 'label' => \__( 'New level', 'ultimate-push-notifications' ), 'options' => array( __CLASS__, 'pmpro_levels' ) ),
			),
			'people'      => array( 'member' => \__( 'The member', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags( array( 'level_name' => \__( 'Level', 'ultimate-push-notifications' ) ) ),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( 'Welcome to {level_name}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Your membership is active. Tap to see what is included.', 'ultimate-push-notifications' ),
				'url'      => '{member_url}',
			),
			'build'       => function ( $args ) {
				list( $level_id, $user_id ) = \array_pad( $args, 2, 0 );
				$level_id = (int) $level_id;
				$name     = \__( 'Cancelled', 'ultimate-push-notifications' );
				if ( $level_id > 0 && \function_exists( 'pmpro_getLevel' ) ) {
					$level = \pmpro_getLevel( $level_id );
					$name  = $level && isset( $level->name ) ? (string) $level->name : (string) $level_id;
				}
				$url = \function_exists( 'pmpro_url' ) ? (string) \pmpro_url( 'account' ) : '';
				$ev  = self::member_event( 'member.pmpro_level_changed', 'pmpro_level:' . (int) $user_id . ':' . $level_id, $user_id, array( 'level' => (string) $level_id ), array( 'level_name' => $name, 'member_url' => $url ), $url );
				return $ev;
			},
		);
	}

	/* ---- MemberPress ------------------------------------------------------------------ */

	public static function mepr_available() {
		return \class_exists( 'MeprEvent' );
	}

	private static function mepr_transaction_completed() {
		return array(
			'key'         => 'member.mepr_transaction_completed',
			'next'        => array( 'key' => 'carriers', 'label' => \__( 'Reach them before they subscribe', 'ultimate-push-notifications' ), 'text' => \__( 'a new member rarely has push yet — send the same message by email, Slack or Telegram when push has nobody to reach.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Membership purchased (MemberPress)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'A MemberPress transaction completes: a membership is bought or renewed.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'mepr_available' ),
			'hooks'       => array( 'mepr-event-transaction-completed' => 1 ),
			'people'      => array( 'member' => \__( 'The member', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags( array( 'membership_name' => \__( 'Membership', 'ultimate-push-notifications' ), 'amount' => \__( 'Amount', 'ultimate-push-notifications' ) ) ),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( 'Welcome to {membership_name}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Your membership is active.', 'ultimate-push-notifications' ),
				'url'      => '{member_url}',
			),
			'build'       => function ( $args ) {
				$evt = isset( $args[0] ) ? $args[0] : null;
				$txn = \is_object( $evt ) && \is_callable( array( $evt, 'get_data' ) ) ? $evt->get_data() : null;
				if ( ! \is_object( $txn ) || empty( $txn->user_id ) ) {
					return null;
				}
				$name = '';
				if ( \is_callable( array( $txn, 'product' ) ) ) {
					$p    = $txn->product();
					$name = \is_object( $p ) && isset( $p->post_title ) ? (string) $p->post_title : '';
				}
				$url = \class_exists( 'MeprOptions' ) && \is_callable( array( 'MeprOptions', 'fetch' ) ) ? (string) \MeprOptions::fetch()->account_page_url() : '';
				return self::member_event( 'member.mepr_transaction_completed', 'mepr_txn:' . ( isset( $txn->id ) ? (int) $txn->id : \uniqid() ), $txn->user_id, array(), array( 'membership_name' => $name, 'amount' => isset( $txn->total ) ? Helpers::money( (float) $txn->total ) : '', 'member_url' => $url ), $url );
			},
		);
	}

	/* ---- Restrict Content Pro ---------------------------------------------------------- */

	public static function rcp_available() {
		return \function_exists( 'rcp_get_membership' );
	}

	private static function rcp_event( $trigger, $membership_id, $suffix ) {
		$m = \function_exists( 'rcp_get_membership' ) ? \rcp_get_membership( (int) $membership_id ) : null;
		if ( ! \is_object( $m ) || ! \is_callable( array( $m, 'get_user_id' ) ) ) {
			return null;
		}
		$name = \is_callable( array( $m, 'get_membership_level_name' ) ) ? (string) $m->get_membership_level_name() : '';
		$url  = \function_exists( 'rcp_get_account_url' ) ? (string) \rcp_get_account_url() : '';
		return self::member_event( $trigger, $suffix . ':' . (int) $membership_id, $m->get_user_id(), array(), array( 'level_name' => $name, 'member_url' => $url ), $url );
	}

	private static function rcp_activated() {
		return array(
			'key'         => 'member.rcp_activated',
			'next'        => array( 'key' => 'carriers', 'label' => \__( 'Reach them before they subscribe', 'ultimate-push-notifications' ), 'text' => \__( 'a new member rarely has push yet — send the same message by email, Slack or Telegram when push has nobody to reach.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Membership activated (Restrict Content Pro)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'A membership becomes active.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'rcp_available' ),
			'hooks'       => array( 'rcp_membership_post_activate' => 1 ),
			'people'      => array( 'member' => \__( 'The member', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags( array( 'level_name' => \__( 'Level', 'ultimate-push-notifications' ) ) ),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( 'Welcome to {level_name}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Your membership is active.', 'ultimate-push-notifications' ),
				'url'      => '{member_url}',
			),
			'build'       => function ( $args ) {
				return self::rcp_event( 'member.rcp_activated', isset( $args[0] ) ? $args[0] : 0, 'rcp_active' );
			},
		);
	}

	private static function rcp_expired() {
		return array(
			'key'         => 'member.rcp_expired',
			'next'        => array( 'key' => 'analytics.goals', 'label' => \__( 'Count the renewals', 'ultimate-push-notifications' ), 'text' => \__( 'a renewal page reached after this notification is credited to it — Goals.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Membership expired (Restrict Content Pro)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'A membership runs out — the moment to invite a renewal.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'rcp_available' ),
			'hooks'       => array( 'rcp_transition_membership_status_expired' => 2 ),
			'people'      => array( 'member' => \__( 'The member', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags( array( 'level_name' => \__( 'Level', 'ultimate-push-notifications' ) ) ),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( 'Your {level_name} membership has ended', 'ultimate-push-notifications' ),
				'body'     => \__( 'Renew to keep your access.', 'ultimate-push-notifications' ),
				'url'      => '{member_url}',
			),
			'build'       => function ( $args ) {
				return self::rcp_event( 'member.rcp_expired', isset( $args[1] ) ? $args[1] : 0, 'rcp_expired' );
			},
		);
	}

	/* ---- WooCommerce Memberships ------------------------------------------------------- */

	public static function wcm_available() {
		return \function_exists( 'wc_memberships_get_user_membership' );
	}

	public static function wcm_statuses() {
		$out = array();
		if ( \function_exists( 'wc_memberships_get_user_membership_statuses' ) ) {
			foreach ( (array) \wc_memberships_get_user_membership_statuses() as $status => $def ) {
				$out[ \preg_replace( '/^wcm-/', '', (string) $status ) ] = isset( $def['label'] ) ? (string) $def['label'] : (string) $status;
			}
		}
		return $out;
	}

	private static function wcm_status_changed() {
		return array(
			'key'         => 'member.wcm_status_changed',
			'next'        => array( 'key' => 'carriers', 'label' => \__( 'Reach them before they subscribe', 'ultimate-push-notifications' ), 'text' => \__( 'a new member rarely has push yet — send the same message by email, Slack or Telegram when push has nobody to reach.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Membership status changes (WooCommerce Memberships)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'A membership becomes active, paused, expired, cancelled…', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'wcm_available' ),
			'hooks'       => array( 'wc_memberships_user_membership_status_changed' => 3 ),
			'params'      => array(
				'status' => array( 'label' => \__( 'New status', 'ultimate-push-notifications' ), 'options' => array( __CLASS__, 'wcm_statuses' ) ),
			),
			'people'      => array( 'member' => \__( 'The member', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags( array( 'plan_name' => \__( 'Plan', 'ultimate-push-notifications' ), 'status_name' => \__( 'Status', 'ultimate-push-notifications' ) ) ),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( '{plan_name}: {status_name}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Tap to see your membership.', 'ultimate-push-notifications' ),
				'url'      => '{member_url}',
			),
			'build'       => function ( $args ) {
				list( $membership, $old, $new ) = \array_pad( $args, 3, null );
				if ( ! \is_object( $membership ) || ! \is_callable( array( $membership, 'get_user_id' ) ) ) {
					return null;
				}
				$status   = \preg_replace( '/^wcm-/', '', (string) $new );
				$statuses = self::wcm_statuses();
				$plan     = \is_callable( array( $membership, 'get_plan' ) ) ? $membership->get_plan() : null;
				$name     = \is_object( $plan ) && \is_callable( array( $plan, 'get_name' ) ) ? (string) $plan->get_name() : '';
				$url      = \function_exists( 'wc_get_page_permalink' ) ? (string) \wc_get_page_permalink( 'myaccount' ) : '';
				$id       = \is_callable( array( $membership, 'get_id' ) ) ? (int) $membership->get_id() : 0;
				return self::member_event( 'member.wcm_status_changed', 'wcm:' . $id . ':' . $status, $membership->get_user_id(), array( 'status' => $status ), array( 'plan_name' => $name, 'status_name' => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status, 'member_url' => $url ), $url );
			},
		);
	}

	/* ---- Ultimate Member --------------------------------------------------------------- */

	public static function um_available() {
		return \function_exists( 'UM' );
	}

	private static function um_approved() {
		return array(
			'key'         => 'member.um_approved',
			'next'        => array( 'key' => 'carriers', 'label' => \__( 'Reach them before they subscribe', 'ultimate-push-notifications' ), 'text' => \__( 'a new member rarely has push yet — send the same message by email, Slack or Telegram when push has nobody to reach.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Member approved (Ultimate Member)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'An administrator approves a pending registration.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'um_available' ),
			'hooks'       => array( 'um_after_user_is_approved' => 1 ),
			'people'      => array( 'member' => \__( 'The member', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags(),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( 'You are in, {member_name}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Your account has been approved.', 'ultimate-push-notifications' ),
				'url'      => '{member_url}',
			),
			'build'       => function ( $args ) {
				$user_id = isset( $args[0] ) ? (int) $args[0] : 0;
				$url     = \function_exists( 'um_user_profile_url' ) ? (string) \um_user_profile_url( $user_id ) : '';
				return self::member_event( 'member.um_approved', 'um_approved:' . $user_id, $user_id, array(), array( 'member_url' => $url ), $url );
			},
		);
	}

	/* ---- LearnDash --------------------------------------------------------------------- */

	public static function ld_available() {
		return \defined( 'LEARNDASH_VERSION' );
	}

	private static function ld_course_completed() {
		return array(
			'key'         => 'member.ld_course_completed',
			'next'        => array( 'key' => 'drip', 'label' => \__( 'A series after the course', 'ultimate-push-notifications' ), 'text' => \__( 'follow up over the next days with the next course, a certificate, a review ask — a series.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Course completed (LearnDash)', 'ultimate-push-notifications' ),
			'group'       => \__( 'Membership', 'ultimate-push-notifications' ),
			'description' => \__( 'A learner finishes a course.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'ld_available' ),
			'hooks'       => array( 'learndash_course_completed' => 1 ),
			'people'      => array( 'member' => \__( 'The learner', 'ultimate-push-notifications' ) ),
			'tags'        => self::tags( array( 'course_title' => \__( 'Course', 'ultimate-push-notifications' ), 'course_url' => \__( 'Course link', 'ultimate-push-notifications' ) ) ),
			'defaults'    => array(
				'audience' => 'member',
				'title'    => \__( 'Course complete: {course_title}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Well done, {member_name}. Tap to see what is next.', 'ultimate-push-notifications' ),
				'url'      => '{course_url}',
			),
			'build'       => function ( $args ) {
				$data   = isset( $args[0] ) && \is_array( $args[0] ) ? $args[0] : array();
				$user   = isset( $data['user'] ) && \is_object( $data['user'] ) ? (int) $data['user']->ID : 0;
				$course = isset( $data['course'] ) && \is_object( $data['course'] ) ? $data['course'] : null;
				if ( ! $course ) {
					return null;
				}
				$url = (string) \get_permalink( $course );
				$ev  = self::member_event( 'member.ld_course_completed', 'ld_course:' . (int) $course->ID . ':' . $user, $user, array(), array( 'course_title' => (string) $course->post_title, 'course_url' => $url, 'member_url' => $url ), $url );
				if ( $ev ) {
					$ev->context['post'] = $course;
				}
				return $ev;
			},
		);
	}

}
