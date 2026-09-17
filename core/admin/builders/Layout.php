<?php namespace UltimatePushNotifications\admin\builders;

use UltimatePushNotifications\lib\Util;

/**
 * The plugin's admin look, for screens that render themselves.
 *
 * AdminPageBuilder wraps a field array in the panel; the screens added
 * since 1.5 (Compose, Automations, Health, the Pro screens) write their own
 * HTML. This gives them the same chrome — the gradient heading, the
 * hints well, the label / input rows, the section titles, the submit bar,
 * the footer — as static helpers, so a template can open the panel, write
 * its rows, and close it, and look like App Configuration does.
 *
 * Markup and class names are the ones assets/css/upn-admin-style.min.css
 * already styles under #cs_addons; nothing here needs its own stylesheet.
 *
 * @package Builder
 * @since 1.6.2
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Layout {

	/**
	 * Open the panel: wrap, heading, optional hints well, body.
	 *
	 * @param string $title     Escaped HTML allowed (the old pages pass a <span class="visibility">).
	 * @param string $sub_title Plain text, escaped here.
	 * @param string $well      HTML for the hints well; '' for none.
	 * @param string $class     Extra class on the wrap.
	 * @return void
	 */
	public static function open( $title, $sub_title = '', $well = '', $class = '' ) {
		echo '<div class="wrap ' . \esc_attr( $class ) . '"><div id="cs_addons"><div class="panel">';
		echo '<div class="panel-heading"><h3 class="title">' . \wp_kses_post( $title ) . '</h3>';
		if ( '' !== (string) $sub_title ) {
			echo '<p>' . \esc_html( $sub_title ) . '</p>';
		}
		echo '</div>';
		echo self::tabs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		echo '<div class="panel-body bg-white no-bottom-margin"><div class="container">';
		if ( '' !== (string) $well ) {
			echo '<div class="well">' . $well . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes
		}
	}

	/**
	 * The tab strip for the current screen's group, if it has more than one tab.
	 *
	 * @return string HTML
	 */
	public static function tabs() {
		return \class_exists( __NAMESPACE__ . '\\Screens' ) ? Screens::tabs_html( Screens::current() ) : '';
	}

	/**
	 * Close the panel, with the footer.
	 *
	 * @return void
	 */
	public static function close() {
		echo '</div></div>' . self::footer() . '</div></div></div>';
	}

	/**
	 * The hints well on its own, for a notice mid-page.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function well( $html ) {
		return '<div class="well">' . $html . '</div>';
	}

	/**
	 * A section title, with an optional description strip.
	 *
	 * @param string $title
	 * @param string $desc  HTML allowed.
	 * @return string
	 */
	public static function section( $title, $desc = '' ) {
		$out = '<div class="section-title">' . \esc_html( $title ) . '</div>';
		if ( '' !== (string) $desc ) {
			$out .= '<p class="section-description">' . $desc . '</p>';
		}
		return $out;
	}

	/**
	 * One label / input row.
	 *
	 * @param string $label Plain text or HTML (a <label for>).
	 * @param string $input HTML.
	 * @param string $desc  HTML, shown under the input.
	 * @param string $class Extra class on the row, e.g. 'no-border'.
	 * @return string
	 */
	public static function field( $label, $input, $desc = '', $class = '' ) {
		$out  = '<div class="form-group ' . \esc_attr( $class ) . '">';
		$out .= '<div class="label"><label>' . $label . '</label></div>';
		$out .= '<div class="input-group">' . $input;
		if ( '' !== (string) $desc ) {
			$out .= '<p class="description">' . $desc . '</p>';
		}
		$out .= '</div></div>';
		return $out;
	}

	/**
	 * The bar a submit button sits in.
	 *
	 * @param string $html Buttons.
	 * @return string
	 */
	public static function submit_bar( $html ) {
		return '<div class="section-submit-button">' . $html . '</div>';
	}

	/**
	 * The footer every panel ends with.
	 *
	 * @return string
	 */
	public static function footer() {
		$more = '';
		if ( \current_user_can( 'install_plugins' ) ) {
			$more = ' ' . \sprintf(
				/* translators: 1: link open, 2: link close */
				\__( 'Check out other %1$sUseful Free Plugins%2$s.', 'ultimate-push-notifications' ),
				'<a href="' . \esc_url( Util::cs_free_plugins() ) . '">',
				'</a>'
			);
		}
		/**
		 * The vendor line at the foot of every screen. White-label replaces it.
		 *
		 * @param string $html
		 */
		$line = \apply_filters(
			'upn_admin_footer_line',
			\sprintf(
				/* translators: 1: vendor link open, 2: close, 3: docs link open, 4: close */
				\__( 'Thank you for choosing %1$sCodeSolz\'s%2$s Software! %3$sDocumentation%4$s', 'ultimate-push-notifications' ),
				'<a href="https://www.codesolz.net" target="_blank">',
				'</a>',
				'<span class="doc-link"><a href="https://docs.codesolz.net/ultimate-push-notifications/" target="_blank">',
				'</a>' . $more . '</span>'
			)
		);
		return '<div class="panel-footer"><p>' . $line . '</p></div>';
	}

}
