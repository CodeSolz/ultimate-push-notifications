<?php namespace UltimatePushNotifications\admin\builders;

/**
 * Tab Builder Class
 *
 * @package Builder
 * @author M.Tuhin <info@codesolz.com>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\admin\builders\FormBuilder;

class TabBuilder {

	/**
	 * Form Generator
	 *
	 * @var type
	 */
	private $Form_Generator;

	public function __construct() {

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();
	}

	/**
	 * Generate tabs and content
	 *
	 * @param [type] $args
	 * @return void
	 */
	public function generate_tabs_and_contents( $args ) {
		if ( empty( $args ) ) {
			return false; }

		$tabs         = '';
		$tab_contents = '';
		$i            = 0;
		foreach ( $args as $id => $item ) {
			if ( isset( $item['tab_name'] ) ) {
				$tabs         .= $this->generate_tab( $id, $item['tab_name'], $i );
				$tab_contents .= $this->generate_tab_content( $id, $item['tab_content'], $i );
				$i++;
			}
		}

		$tabs_content = $this->generate_tabs_content_section( $tabs, $tab_contents );

		return $tabs_content;
	}

	/**
	 * Generate Tab
	 *
	 * @param [type] $id
	 * @param [type] $name
	 * @return void
	 */
	private function generate_tab( $id, $name, $index ) {
		\ob_start();
		?>
			<li class="<?php echo 0 == $index ? 'active' : ''; ?>">
				<a class="" data-id="<?php echo $id; ?>" ><?php echo $name; ?></a>
			</li>
		<?php
		$html = \ob_get_clean();

		return $html;
	}

	/**
	 * Generate tab content
	 *
	 * @param [type] $id
	 * @param [type] $fields
	 * @return void
	 */
	private function generate_tab_content( $id, $fields, $index ) {
		$no_settings = '';
		if ( empty( $fields ) ) {
			$no_settings = __( 'Sorry! No options available.', 'ultimate-push-notifications' );
		}

		\ob_start();
		?>
			<div class="<?php echo $id; ?> <?php echo 0 == $index ? '' : ' hidden'; ?>">
				<?php echo empty( $no_settings ) ? $this->Form_Generator->generate_html_fields( $fields ) : $no_settings; ?>
			</div>
		<?php
		$html = \ob_get_clean();

		return $html;
	}

	/**
	 * Tabs content section
	 *
	 * @param [type] $tabs
	 * @param [type] $tab_contents
	 * @return void
	 */
	private function generate_tabs_content_section( $tabs, $tab_contents ) {
		\ob_start();
		?>
			<div class="upn-tabs">
				<div class="tabs"><ul class="item-tabs"><?php echo $tabs; ?></ul></div>
				<div class="tabs-content"><?php echo $tab_contents; ?></div>
			</div>
		<?php
		$html = \ob_get_clean();

		return $html;
	}

}
