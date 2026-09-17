<?php namespace UltimatePushNotifications\automation;

/**
 * One thing that happened, described so a rule can decide whether to act on
 * it, who to tell, and what to say.
 *
 * A trigger builds an Event from the raw hook arguments; the engine never
 * sees WooCommerce or BuddyPress objects directly. That is what lets one
 * rule editor serve every integration, and what lets the tests exercise the
 * whole path without either plugin installed.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Event {

	/** @var string Trigger key, e.g. "woo.order_status_changed". */
	public $trigger;

	/**
	 * Values a rule may match on, e.g. status_to => "processing". A rule that
	 * leaves a param blank matches any value.
	 *
	 * @var array<string,string>
	 */
	public $params = array();

	/**
	 * Merge-tag context: post, user, and an "extra" map of ad-hoc tags the
	 * trigger documents, e.g. order_total.
	 *
	 * @var array
	 */
	public $context = array();

	/**
	 * Event-specific audiences: name => user ids. "buyer", "product_authors",
	 * "recipient", "friends"… A rule picks one of these by name.
	 *
	 * @var array<string,int[]>
	 */
	public $people = array();

	/** @var int The user whose action caused the event; left out unless the rule says otherwise. */
	public $actor = 0;

	/** @var string Identifies this occurrence, so it is handled once per request and re-notifications replace rather than stack. */
	public $key = '';

	/** @var string Where a click should land unless the rule overrides it. */
	public $url = '';

	/** @var string A picture the notification may carry, e.g. the product image. */
	public $image = '';

	/**
	 * @param string $trigger
	 * @param array  $args    Any of the public properties.
	 */
	public function __construct( $trigger, array $args = array() ) {
		$this->trigger = (string) $trigger;

		foreach ( array( 'params', 'context', 'people', 'actor', 'key', 'url', 'image' ) as $prop ) {
			if ( \array_key_exists( $prop, $args ) ) {
				$this->{$prop} = $args[ $prop ];
			}
		}

		$this->actor = (int) $this->actor;
		if ( '' === $this->key ) {
			$this->key = $this->trigger . ':' . \uniqid( '', true );
		}
		if ( ! isset( $this->context['extra'] ) || ! \is_array( $this->context['extra'] ) ) {
			$this->context['extra'] = array();
		}
	}

	/**
	 * @param string $name
	 * @return int[] Unique, positive, actor included (the audience resolver removes them).
	 */
	public function people( $name ) {
		if ( empty( $this->people[ $name ] ) ) {
			return array();
		}
		$ids = \array_map( 'intval', (array) $this->people[ $name ] );
		$ids = \array_filter( $ids, function ( $id ) { return $id > 0; } );
		return \array_values( \array_unique( $ids ) );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function param( $name ) {
		return isset( $this->params[ $name ] ) ? (string) $this->params[ $name ] : '';
	}

	/**
	 * @param string $tag
	 * @param mixed  $value
	 * @return void
	 */
	public function extra( $tag, $value ) {
		$this->context['extra'][ $tag ] = $value;
	}

}
