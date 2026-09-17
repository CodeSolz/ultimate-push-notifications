<?php namespace UltimatePushNotifications\optin;

use UltimatePushNotifications\transport\Vapid;

/**
 * How visitors are asked to subscribe.
 *
 * The permission prompt is a one-shot: a browser that hears "no" to a cold
 * prompt on page one will not ask again for months, and Chrome quietly
 * suppresses sites that get denied a lot. So the plugin never calls
 * Notification.requestPermission() on its own. It asks first with its own
 * dismissible bar — after a delay, after N page views, after scrolling — and
 * only hands off to the native prompt when the visitor clicks Allow.
 *
 * Three surfaces, all driven by the same client:
 *   - the soft-ask bar (settings below)
 *   - a floating bell
 *   - a button anywhere, via [upn_subscribe] or the block
 *
 * @package OptIn
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class OptIn {

	const OPTION = 'upn_optin';

	/**
	 * @return array
	 */
	public static function defaults() {
		return array(
			'prompt_enabled'      => false,
			'prompt_title'        => \__( 'Get notified', 'ultimate-push-notifications' ),
			'prompt_text'         => \__( 'We\'ll send you a notification when there\'s something new. No email required.', 'ultimate-push-notifications' ),
			'prompt_allow'        => \__( 'Allow', 'ultimate-push-notifications' ),
			'prompt_later'        => \__( 'Not now', 'ultimate-push-notifications' ),
			'prompt_delay'        => 5,
			'prompt_pageviews'    => 2,
			'prompt_scroll'       => 0,
			'prompt_dismiss_days' => 7,
			'prompt_position'     => 'bottom',
			'prompt_logged_in'    => true,
			'prompt_anonymous'    => true,
			'bell_enabled'        => false,
			'bell_position'       => 'bottom-right',
			'bell_color'          => '#2271b1',
		);
	}

	/**
	 * @return array
	 */
	public static function settings() {
		$saved = \get_option( self::OPTION );
		return self::sanitize( \is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param array $input
	 * @return array
	 */
	public static function sanitize( $input ) {
		$d     = self::defaults();
		$input = \is_array( $input ) ? $input : array();

		$text = function ( $key, $max ) use ( $input, $d ) {
			$v = isset( $input[ $key ] ) ? \wp_strip_all_tags( \trim( (string) $input[ $key ] ) ) : $d[ $key ];
			return \mb_substr( '' === $v ? $d[ $key ] : $v, 0, $max );
		};

		$position = isset( $input['prompt_position'] ) && \in_array( $input['prompt_position'], array( 'top', 'bottom' ), true ) ? $input['prompt_position'] : $d['prompt_position'];
		$bell_pos = isset( $input['bell_position'] ) && \in_array( $input['bell_position'], array( 'bottom-right', 'bottom-left' ), true ) ? $input['bell_position'] : $d['bell_position'];
		$color    = isset( $input['bell_color'] ) && \preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $input['bell_color'] ) ? \strtolower( $input['bell_color'] ) : $d['bell_color'];

		return array(
			'prompt_enabled'      => ! empty( $input['prompt_enabled'] ),
			'prompt_title'        => $text( 'prompt_title', 80 ),
			'prompt_text'         => $text( 'prompt_text', 240 ),
			'prompt_allow'        => $text( 'prompt_allow', 30 ),
			'prompt_later'        => $text( 'prompt_later', 30 ),
			'prompt_delay'        => isset( $input['prompt_delay'] ) ? \max( 0, \min( 300, (int) $input['prompt_delay'] ) ) : $d['prompt_delay'],
			'prompt_pageviews'    => isset( $input['prompt_pageviews'] ) ? \max( 1, \min( 20, (int) $input['prompt_pageviews'] ) ) : $d['prompt_pageviews'],
			'prompt_scroll'       => isset( $input['prompt_scroll'] ) ? \max( 0, \min( 100, (int) $input['prompt_scroll'] ) ) : $d['prompt_scroll'],
			'prompt_dismiss_days' => isset( $input['prompt_dismiss_days'] ) ? \max( 1, \min( 365, (int) $input['prompt_dismiss_days'] ) ) : $d['prompt_dismiss_days'],
			'prompt_position'     => $position,
			'prompt_logged_in'    => ! isset( $input['prompt_logged_in'] ) ? $d['prompt_logged_in'] : ! empty( $input['prompt_logged_in'] ),
			'prompt_anonymous'    => ! isset( $input['prompt_anonymous'] ) ? $d['prompt_anonymous'] : ! empty( $input['prompt_anonymous'] ),
			'bell_enabled'        => ! empty( $input['bell_enabled'] ),
			'bell_position'       => $bell_pos,
			'bell_color'          => $color,
		);
	}

	/**
	 * What the client needs, as localized data.
	 *
	 * @return array
	 */
	public static function client_config() {
		$s        = self::settings();
		$audience = \is_user_logged_in() ? $s['prompt_logged_in'] : $s['prompt_anonymous'];

		return array(
			'prompt' => array(
				'enabled'     => $s['prompt_enabled'] && $audience && Vapid::has_keys(),
				'title'       => $s['prompt_title'],
				'text'        => $s['prompt_text'],
				'allow'       => $s['prompt_allow'],
				'later'       => $s['prompt_later'],
				'delay'       => $s['prompt_delay'],
				'pageviews'   => $s['prompt_pageviews'],
				'scroll'      => $s['prompt_scroll'],
				'dismissDays' => $s['prompt_dismiss_days'],
				'position'    => $s['prompt_position'],
			),
			'bell'   => array(
				'enabled'  => $s['bell_enabled'] && Vapid::has_keys(),
				'position' => $s['bell_position'],
				'color'    => $s['bell_color'],
				'labelOn'  => \__( 'Notifications are on. Click to turn off.', 'ultimate-push-notifications' ),
				'labelOff' => \__( 'Turn on notifications', 'ultimate-push-notifications' ),
			),
			'button' => array(
				'subscribed'   => \__( 'Notifications on', 'ultimate-push-notifications' ),
				'unsubscribed' => \__( 'Get notifications', 'ultimate-push-notifications' ),
			),
		);
	}

	/**
	 * Hook up shortcode and block.
	 *
	 * @return void
	 */
	public static function boot() {
		\add_shortcode( 'upn_subscribe', array( __CLASS__, 'shortcode' ) );
		\add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * [upn_subscribe text="…" subscribed_text="…" class="…"]
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = \shortcode_atts(
			array(
				'text'            => '',
				'subscribed_text' => '',
				'class'           => '',
			),
			$atts,
			'upn_subscribe'
		);

		return self::button_markup( $atts['text'], $atts['subscribed_text'], $atts['class'] );
	}

	/**
	 * The button markup the client enhances.
	 *
	 * Works without JavaScript as a no-op: a visitor with scripts blocked sees
	 * a button that does nothing rather than a broken page.
	 *
	 * @param string $text
	 * @param string $subscribed_text
	 * @param string $class
	 * @return string
	 */
	public static function button_markup( $text = '', $subscribed_text = '', $class = '' ) {
		if ( ! Vapid::has_keys() ) {
			return '';
		}

		$labels = self::client_config()['button'];
		$text   = '' !== $text ? $text : $labels['unsubscribed'];
		$sub    = '' !== $subscribed_text ? $subscribed_text : $labels['subscribed'];

		return \sprintf(
			'<button type="button" class="upn-subscribe-button %s" data-upn-subscribe data-upn-label-off="%s" data-upn-label-on="%s">%s</button>',
			\esc_attr( \sanitize_html_class( $class ) ),
			\esc_attr( $text ),
			\esc_attr( $sub ),
			\esc_html( $text )
		);
	}

	/**
	 * A server-rendered block, registered with an inline editor script so no
	 * build step is needed.
	 *
	 * @return void
	 */
	public static function register_block() {
		if ( ! \function_exists( 'register_block_type' ) ) {
			return;
		}

		\wp_register_script(
			'upn-subscribe-block',
			false,
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			CS_UPN_VERSION,
			true
		);

		$editor = <<<'JS'
( function ( blocks, element, components, blockEditor, i18n ) {
	var el = element.createElement, __ = i18n.__;
	blocks.registerBlockType( 'upn/subscribe-button', {
		title: __( 'Push Subscribe Button', 'ultimate-push-notifications' ),
		icon: 'bell',
		category: 'widgets',
		attributes: {
			text: { type: 'string', default: '' },
			subscribedText: { type: 'string', default: '' }
		},
		edit: function ( props ) {
			var a = props.attributes;
			return el( element.Fragment, {},
				el( blockEditor.InspectorControls, {},
					el( components.PanelBody, { title: __( 'Labels', 'ultimate-push-notifications' ) },
						el( components.TextControl, { label: __( 'Button text', 'ultimate-push-notifications' ), value: a.text, onChange: function ( v ) { props.setAttributes( { text: v } ); } } ),
						el( components.TextControl, { label: __( 'Text when subscribed', 'ultimate-push-notifications' ), value: a.subscribedText, onChange: function ( v ) { props.setAttributes( { subscribedText: v } ); } } )
					)
				),
				el( 'button', { type: 'button', className: 'upn-subscribe-button', disabled: true }, a.text || __( 'Get notifications', 'ultimate-push-notifications' ) )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n );
JS;
		\wp_add_inline_script( 'upn-subscribe-block', $editor );

		\register_block_type(
			'upn/subscribe-button',
			array(
				'editor_script'   => 'upn-subscribe-block',
				'render_callback' => function ( $attributes ) {
					return self::button_markup(
						isset( $attributes['text'] ) ? $attributes['text'] : '',
						isset( $attributes['subscribedText'] ) ? $attributes['subscribedText'] : ''
					);
				},
				'attributes'      => array(
					'text'           => array( 'type' => 'string', 'default' => '' ),
					'subscribedText' => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

}
