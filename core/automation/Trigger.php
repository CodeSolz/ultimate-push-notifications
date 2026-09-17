<?php namespace UltimatePushNotifications\automation;

/**
 * A trigger: which hooks to listen on, how to turn their arguments into an
 * Event, and what that Event offers a rule (params to match, people to
 * notify, tags to write with).
 *
 * Triggers are plain definitions (see the triggers/ directory) so that an
 * add-on can register its own through the upn_automation_triggers filter
 * without subclassing anything.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Trigger {

	/** @var array */
	private $def;

	/**
	 * @param array $def key, label, group, description, available, hooks,
	 *                   build, params, people, tags, defaults.
	 */
	public function __construct( array $def ) {
		$this->def = $def + array(
			'label'       => $def['key'],
			'group'       => 'WordPress',
			'description' => '',
			'available'   => true,
			'hooks'       => array(),
			'build'       => null,
			'params'      => array(),
			'people'      => array(),
			'tags'        => array(),
			'defaults'    => array(),
			'next'        => array(),
		);
	}

	/**
	 * The step after this trigger that Pro completes: array( 'key' => entitlement, 'label' => …, 'text' => … ), or empty.
	 *
	 * @return array
	 */
	public function next() {
		return \is_array( $this->def['next'] ) ? $this->def['next'] : array();
	}

	/** @return string */
	public function key() {
		return (string) $this->def['key'];
	}

	/** @return string */
	public function label() {
		return (string) $this->def['label'];
	}

	/** @return string */
	public function group() {
		return (string) $this->def['group'];
	}

	/** @return string */
	public function description() {
		return (string) $this->def['description'];
	}

	/**
	 * Is the integration this trigger listens to active?
	 *
	 * @return bool
	 */
	public function available() {
		$a = $this->def['available'];
		return \is_callable( $a ) ? (bool) \call_user_func( $a ) : (bool) $a;
	}

	/**
	 * @return array<string,int> hook name => accepted args.
	 */
	public function hooks() {
		return (array) $this->def['hooks'];
	}

	/**
	 * Rule-matchable params: key => array( 'label' => …, 'options' => array|callable ).
	 * Options are value => label; the editor adds an "Any" choice itself.
	 *
	 * @return array
	 */
	public function params() {
		$out = array();
		foreach ( (array) $this->def['params'] as $key => $p ) {
			$options = isset( $p['options'] ) ? $p['options'] : array();
			if ( \is_callable( $options ) ) {
				$options = (array) \call_user_func( $options );
			}
			$out[ $key ] = array(
				'label'   => isset( $p['label'] ) ? $p['label'] : $key,
				'options' => $options,
			);
		}
		return $out;
	}

	/**
	 * Just the param names — no option callables run. This is what the rule
	 * store needs on every request; params() is for the editor.
	 *
	 * @return string[]
	 */
	public function param_keys() {
		return \array_map( 'strval', \array_keys( (array) $this->def['params'] ) );
	}

	/**
	 * Event-specific audiences this trigger can fill: name => label.
	 *
	 * @return array<string,string>
	 */
	public function people() {
		return (array) $this->def['people'];
	}

	/**
	 * Merge tags this trigger documents: tag => label.
	 *
	 * @return array<string,string>
	 */
	public function tags() {
		return (array) $this->def['tags'];
	}

	/**
	 * Suggested copy for a new rule: title, body, url, audience.
	 *
	 * @return array
	 */
	public function defaults() {
		return (array) $this->def['defaults'];
	}

	/**
	 * Turn hook arguments into an Event, or null to ignore this occurrence.
	 *
	 * @param array $args
	 * @return Event|null
	 */
	public function build( array $args ) {
		if ( ! \is_callable( $this->def['build'] ) ) {
			return null;
		}

		try {
			$event = \call_user_func( $this->def['build'], $args );
		} catch ( \Throwable $e ) {
			// A broken integration must never take the triggering request down with it.
			return null;
		}

		if ( ! $event instanceof Event ) {
			return null;
		}
		$event->trigger = $this->key();
		return $event;
	}

}
