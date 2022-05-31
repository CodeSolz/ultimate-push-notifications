<?php namespace UltimatePushNotifications\admin\options\pages;

/**
 * Class: Set Notifications
 *
 * @package Options
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\builders\FormBuilder;
use UltimatePushNotifications\admin\builders\AdminPageBuilder;


class SetNotifications {

	/**
	 * Hold page generator class
	 *
	 * @var type
	 */
	private $Admin_Page_Generator;

	/**
	 * Form Generator
	 *
	 * @var type
	 */
	private $Form_Generator;


	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();

	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_page( $args, $option ) {
		global $wp_roles;

		//tabs
		$section_tabs = \apply_filters( 'upn_set_notification_tabs', array(
			'tab1' => array(
				'type'  => 'tab',
				'title' => __( 'Default', 'ultimate-push-notifications' )
			)
		) );

		

		$user_registration_notifications = array();
		if ( is_super_admin() ) {
			$user_roles = array();
			if ( $wp_roles->roles ) {
				foreach ( $wp_roles->roles as $key => $role ) {
					$user_roles += array(
						"cs_set_notifications[{$key}Check]"             => array(
							'title'    => $role['name'],
							'type'     => 'checkbox',
							'value'    => FormBuilder::get_value( "{$key}Check", $option, '' ),
							'desc_tip' => sprintf( __( 'Please check this if you want to get notification when %s get registered.', 'ultimate-push-notifications' ), '<b>' . $role['name'] . '</b>' ),
						),
					);
				}
			}

			$user_registration_notifications = array(
				'st1' => array(
					'type'     => 'section_title',
					'title'    => __( 'User Registration Notifications ( visible to administrator only )', 'ultimate-push-notifications' ),
					'desc_tip' => __( 'Please set the following configuration to get notifications when user get registered.', 'ultimate-push-notifications' ),
				),
			) + $user_roles;

		}

		$woocommerce_notifications = array(
			'st2'                                        => array(
				'type'     => 'section_title',
				'title'    => __( 'WooCommerce Notifications', 'ultimate-push-notifications' ),
				'desc_tip' => __( 'Please set the following configuration to get WooCommerce related notifications.', 'ultimate-push-notifications' ),
			),
			'cs_set_notifications[add_to_cart]'          => array(
				'title'    => __( 'Add To Cart', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when a product get added to cart. You can change the notification message with your won words. You can use : {product_title}, {product_price}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[addToCartCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'addToCartCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[addToCartTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'addToCartTitle', $option, 'New Product added to cart' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[addToCartBody]'  => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'addToCartBody', $option, 'A new product was added to cart: {product_title}' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[product_sold]'         => array(
				'title'    => __( 'Product sold', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when a product get sold. You can change the notification message with your won words.You can use: {first_name}, {last_name}, {full_name}, {total}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[productSoldCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'productSoldCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[productSoldTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'productSoldTitle', $option, 'New Product Sold' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[productSoldBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'productSoldBody', $option, 'New order from: {full_name} for a total of: {total}' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[order_status_updated]' => array(
				'title'    => __( 'Order status update', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when Order status update. You can change the notification message with your won words.You can use: {first_name}, {last_name}, {full_name}, {order_id}, {status_from}, {status_to}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[orderStatusUpdatedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'orderStatusUpdatedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[orderStatusUpdatedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'orderStatusUpdatedTitle', $option, 'Order status changed' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[orderStatusUpdatedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'orderStatusUpdatedBody', $option, 'The status of order: #{order_id} requested by {full_name} was changed from {status_from} to {status_to}.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
		);

		$fields          = $section_tabs + $user_registration_notifications + $woocommerce_notifications;
		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$swal_title           = __( 'Saving Settings', 'ultimate-push-notifications' );
		$btn_txt              = __( 'Save settings', 'ultimate-push-notifications' );
		$update_hidden_fields = array();
		if ( ! empty( $option ) ) {
			$swal_title = __( 'Updating Settings', 'ultimate-push-notifications' );
			$btn_txt    = __( 'Update settings', 'ultimate-push-notifications' );

			$update_hidden_fields = array(
				'cs_set_notifications_update[id]' => array(
					'id'    => 'id',
					'type'  => 'hidden',
					'value' => 1,
				),
			);

		}

		$hidden_fields = array_merge_recursive(
			array(
				'method'     => array(
					'id'    => 'method',
					'type'  => 'hidden',
					'value' => "admin\\options\\functions\\SetNotifications@save",
				),
				'swal_title' => array(
					'id'    => 'swal_title',
					'type'  => 'hidden',
					'value' => $swal_title,
				),
			),
			$update_hidden_fields
		);

		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']   = $btn_txt;
		$args['show_btn']   = true;
		$args['body_class'] = 'no-bottom-margin';

		return $this->Admin_Page_Generator->generate_page( $args );
	}

}

