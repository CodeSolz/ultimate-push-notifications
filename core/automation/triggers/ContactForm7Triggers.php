<?php namespace UltimatePushNotifications\automation\triggers;

use UltimatePushNotifications\automation\Event;

/**
 * Contact Form 7.
 *
 * Every submitted field is exposed as a merge tag, {field_your_name} for a
 * field named "your-name", so custom forms work without a mapping screen.
 * The old integration only knew the four default field names.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class ContactForm7Triggers {

	/** Longest value a single field contributes to the summary. */
	const VALUE_CHARS = 120;

	/**
	 * @return array[]
	 */
	public static function definitions() {
		return array( self::submission() );
	}

	/**
	 * @return bool
	 */
	public static function available() {
		return \class_exists( 'WPCF7_Submission' );
	}

	/**
	 * @return array form id => title
	 */
	public static function form_options() {
		if ( ! \function_exists( 'get_posts' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) \get_posts( array( 'post_type' => 'wpcf7_contact_form', 'posts_per_page' => 100, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$out[ (string) $p->ID ] = $p->post_title;
		}
		return $out;
	}

	/**
	 * "your-name" → "field_your_name". Only [a-z0-9_] survive, matching what
	 * MergeTags::render() recognises.
	 *
	 * @param string $name
	 * @return string
	 */
	public static function tag_for( $name ) {
		$tag = \strtolower( \preg_replace( '/[^a-z0-9_]+/i', '_', (string) $name ) );
		return 'field_' . \trim( $tag, '_' );
	}

	/**
	 * Flatten a submitted value: arrays (checkboxes) become a comma list.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function flatten( $value ) {
		if ( \is_array( $value ) ) {
			$value = \implode( ', ', \array_map( array( __CLASS__, 'flatten' ), $value ) );
		}
		$value = \trim( \preg_replace( '/\s+/u', ' ', \wp_strip_all_tags( (string) $value ) ) );
		return \mb_substr( $value, 0, self::VALUE_CHARS );
	}

	private static function submission() {
		return array(
			'key'         => 'cf7.submission',
			'label'       => \__( 'Form submitted', 'ultimate-push-notifications' ),
			'group'       => 'Contact Form 7',
			'description' => \__( 'A Contact Form 7 form is submitted and its mail is sent.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'wpcf7_mail_sent' => 1 ),
			'params'      => array(
				'form_id' => array( 'label' => \__( 'Form', 'ultimate-push-notifications' ), 'options' => array( __CLASS__, 'form_options' ) ),
			),
			'people'      => array( 'submitter' => \__( 'The submitter (if logged in)', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'form_title'   => \__( 'Form name', 'ultimate-push-notifications' ),
				'form_summary' => \__( 'All fields, one per line', 'ultimate-push-notifications' ),
				'field_xxx'    => \__( 'Any field, e.g. {field_your_email}', 'ultimate-push-notifications' ),
				'form_url'     => \__( 'Page the form was on', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'roles',
				'roles'    => array( 'administrator' ),
				'title'    => \__( '{form_title}: new submission', 'ultimate-push-notifications' ),
				'body'     => '{form_summary}',
				'url'      => '{form_url}',
			),
			'build'       => function ( $args ) {
				$form = isset( $args[0] ) && \is_object( $args[0] ) ? $args[0] : null;
				if ( ! $form ) {
					return null;
				}
				$submission = \class_exists( 'WPCF7_Submission' ) ? \WPCF7_Submission::get_instance() : null;
				$posted     = $submission && \is_callable( array( $submission, 'get_posted_data' ) ) ? (array) $submission->get_posted_data() : array();
				$form_id    = \is_callable( array( $form, 'id' ) ) ? (int) $form->id() : 0;
				$title      = \is_callable( array( $form, 'title' ) ) ? (string) $form->title() : '';
				$page_url   = $submission && \is_callable( array( $submission, 'get_meta' ) ) ? (string) $submission->get_meta( 'url' ) : '';
				$actor      = Helpers::actor();

				$event = new Event( 'cf7.submission', array(
					'key'     => 'cf7:' . $form_id . ':' . \md5( \serialize( $posted ) . \microtime( true ) ),
					'actor'   => $actor,
					'params'  => array( 'form_id' => (string) $form_id ),
					'people'  => array( 'submitter' => $actor > 0 ? array( $actor ) : array() ),
					'context' => array( 'user' => Helpers::user( $actor ) ),
					'url'     => '' !== $page_url ? $page_url : \admin_url( 'admin.php?page=wpcf7&post=' . $form_id . '&action=edit' ),
				) );

				$lines = array();
				foreach ( $posted as $name => $value ) {
					if ( 0 === \strpos( (string) $name, '_' ) ) {
						continue; // CF7 internals (_wpcf7, _wpnonce…).
					}
					$flat = self::flatten( $value );
					$event->extra( self::tag_for( $name ), $flat );
					if ( '' !== $flat ) {
						$lines[] = $name . ': ' . $flat;
					}
				}

				$event->extra( 'form_title', $title );
				$event->extra( 'form_summary', \implode( "\n", $lines ) );
				$event->extra( 'form_url', $event->url );
				return $event;
			},
		);
	}

}
