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
use UltimatePushNotifications\admin\builders\TabBuilder;
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

	/**
	 * Tab Generator
	 *
	 * @var [type]
	 */
	private $Tab_Generator;


	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();

		$this->Tab_Generator = new TabBuilder();

	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_page( $args, $option ) {
		global $wp_roles;

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
				'desc_tip' => __( 'Please check the checkbox to get notification when a product get added to cart. You can change the notification message with your own words. You can use : {product_title}, {product_price}', 'ultimate-push-notifications' ),
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
				'desc_tip' => __( 'Please check the checkbox to get notification when a product get sold. You can change the notification message with your own words.You can use: {first_name}, {last_name}, {full_name}, {total}', 'ultimate-push-notifications' ),
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
				'desc_tip' => __( 'Please check the checkbox to get notification when Order status update. You can change the notification message with your own words.You can use: {first_name}, {last_name}, {full_name}, {order_id}, {status_from}, {status_to}', 'ultimate-push-notifications' ),
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

		$components = array(
			'wp-default-event' => array(
				'tab_name'    => __( 'WordPress Default Events', 'ultimate-push-notifications' ),
				'tab_content' => apply_filters( 'upn_user_registration_notifications_fields', $user_registration_notifications ),
			),
			'woocommerce'      => array(
				'tab_name'    => __( 'WooCommerce', 'ultimate-push-notifications' ),
				'tab_content' => apply_filters( 'upn_woocommerce_notifications_fields', $woocommerce_notifications ),
			),
			'buddypress'       => array(
				'tab_name'    => __( 'BuddyPress', 'ultimate-push-notifications' ),
				'tab_content' => apply_filters( 'upn_buddypress_fields', self::upn_buddypress_fields( $option ) ),
			),
			'contact-form-7'       => array(
				'tab_name'    => __( 'Contact Form 7', 'ultimate-push-notifications' ),
				'tab_content' => apply_filters( 'upn_wpcf7_fields', self::upn_wpcf7_fields( $option ) ),
			),
			'the-events-calendar'       => array(
				'tab_name'    => __( 'The Events Calendar', 'ultimate-push-notifications' ),
				'tab_content' => apply_filters( 'upn_the_events_calendar_fields', self::upn_the_events_calendar_fields( $option ) ),
			),
		);

		$args['content']      = $this->Tab_Generator->generate_tabs_and_contents( $components );
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

	/**
	 * BuddyPress Fields
	 *
	 * @return void
	 */
	public static function upn_buddypress_fields( $option = array() ) {
		return array(
			'st1'                                     => array(
				'type'     => 'section_title',
				'title'    => __( 'BuddyPress Notifications - Friends', 'ultimate-push-notifications' ),
				'desc_tip' => __( 'Please set the following configuration to get friendship related notifications.', 'ultimate-push-notifications' ),
			),
			'cs_set_notifications[on_friend_request]' => array(
				'title'    => __( 'New Friend Request', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when someone send friend request. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[bpFriendRequestCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpFriendRequestCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpFriendRequestTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpFriendRequestTitle', $option, 'New Friend Request Received!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpFriendRequestBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpFriendRequestBody', $option, '{user_full_name}, sent you friend request on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[on_friend_request_accepted]' => array(
				'title'    => __( 'Friend Request Accepted', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when accept your friend request. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[bpFriendRequestAcceptedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpFriendRequestAcceptedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpFriendRequestAcceptedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpFriendRequestAcceptedTitle', $option, 'Friend Request Accepted!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpFriendRequestAcceptedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpFriendRequestAcceptedBody', $option, '{user_full_name}, accepted your friend request on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[on_friend_request_rejected]' => array(
				'title'    => __( 'Friend Request Rejected', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when reject your friend request. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[bpFriendRequestRejectedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpFriendRequestRejectedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpFriendRequestRejectedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpFriendRequestRejectedTitle', $option, 'Friend Request Rejected!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpFriendRequestRejectedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpFriendRequestRejectedBody', $option, '{user_full_name}, rejected your friend request on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[on_friend_request_rejected]' => array(
				'title'    => __( 'Friend Request Rejected', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when reject your friend request. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[bpFriendRequestRejectedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpFriendRequestRejectedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpFriendRequestRejectedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpFriendRequestRejectedTitle', $option, 'Friend Request Rejected!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpFriendRequestRejectedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpFriendRequestRejectedBody', $option, '{user_full_name}, rejected your friend request on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[on_friend_request_cancelled]' => array(
				'title'    => __( 'Friend Request Cancelled', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when friend request get cancelled. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				'options'  => array(
					'cs_set_notifications[bpFriendRequestCancelledCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpFriendRequestCancelledCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpFriendRequestCancelledTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpFriendRequestCancelledTitle', $option, 'Friend Request Cancelled!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpFriendRequestCancelledBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpFriendRequestCancelledBody', $option, '{user_full_name}, cancelled friend request on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'st2'                                     => array(
				'type'     => 'section_title',
				'title'    => __( 'BuddyPress Notifications - Activity', 'ultimate-push-notifications' ),
				'desc_tip' => __( 'Please set the following configuration to get message related notifications.', 'ultimate-push-notifications' ),
			),
			
			'cs_set_notifications[on_activity_post_publish]' => array(
				'title'    => __( 'New global activity', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when any user publish a global activity post in their wall. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}, {activity_post}', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpActivityPostPublishCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpActivityPostPublishCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						
					),
					'cs_set_notifications[bpActivityPostPublishTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpActivityPostPublishTitle', $option, 'New Activity Published!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpActivityPostPublishBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpActivityPostPublishBody', $option, '{user_full_name}, published a new activity post on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174
						),
					),

				),
			),
			

			'cs_set_notifications[on_activity_post_type_publish]' => array(
				'title'    => __( 'New Custom Activity Post Type', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when any user publish a custom activity post type. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpActivityPostTypePublishCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpActivityPostTypePublishCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						
					),
					'cs_set_notifications[bpActivityPostTypePublishTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpActivityPostTypePublishTitle', $option, 'New Post Type Published!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38
						),
					),
					'cs_set_notifications[bpActivityPostTypePublishBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpActivityPostTypePublishBody', $option, '{user_full_name}, published a new activity post type on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174
						),
					),

				),
			),
			'cs_set_notifications[on_activity_post_type_updated]' => array(
				'title'    => __( 'Custom Activity Post Type Update', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when any user update a custom activity post type. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpActivityPostTypeUpdatedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpActivityPostTypeUpdatedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						
					),
					'cs_set_notifications[bpActivityPostTypeUpdatedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpActivityPostTypeUpdatedTitle', $option, 'Post Type Updated!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38
						),
					),
					'cs_set_notifications[bpActivityPostTypeUpdatedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpActivityPostTypeUpdatedBody', $option, '{user_full_name}, updated activity post type on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174
						),
					),

				),
			),

			'cs_set_notifications[on_activity_post_type_unpublished]' => array(
				'title'    => __( 'Custom Activity Post Type Deleted', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when any user delete a custom activity post type. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpActivityPostTypeUnpublishedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpActivityPostTypeUnpublishedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						
					),
					'cs_set_notifications[bpActivityPostTypeUnpublishedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpActivityPostTypeUnpublishedTitle', $option, 'Post Type Unpublished!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38
						),
					),
					'cs_set_notifications[bpActivityPostTypeUnpublishedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpActivityPostTypeUnpublishedBody', $option, '{user_full_name}, Unpublished activity post type on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174
						),
					),

				),
			),
			'cs_set_notifications[on_activity_post_comment]' => array(
				'title'    => __( 'New Comment On Post', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when any user post a comment on your post. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpActivityPostCommentCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpActivityPostCommentCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						
					),
					'cs_set_notifications[bpActivityPostCommentTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpActivityPostCommentTitle', $option, 'New Comment!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38
						),
					),
					'cs_set_notifications[bpActivityPostCommentBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpActivityPostCommentBody', $option, '{user_full_name}, posted a new comment on your post on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174
						),
					),

				),
			),
			

			'st3'                                     => array(
				'type'     => 'section_title',
				'title'    => __( 'BuddyPress Notifications - Message', 'ultimate-push-notifications' ),
				'desc_tip' => __( 'Please set the following configuration to get message related notifications.', 'ultimate-push-notifications' ),
			),
			'cs_set_notifications[on_new_message_receive]' => array(
				'title'    =>  __( 'New Message ', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when you receive a new message. You can change the notification message with your own words.You can use: {user_full_name}, {user_first_name}, {user_last_name}, {user_display_name}, {msg_title}, {message}', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpNewMessageReceiveCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpNewMessageReceiveCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpNewMessageReceiveTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpNewMessageReceiveTitle', $option, 'New Message Received!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpNewMessageReceiveBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpNewMessageReceiveBody', $option, '{user_full_name}, send you a new message on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),

			'st4'                                     => array(
				'type'     => 'section_title',
				'title'    => __( 'BuddyPress Notifications - Groups', 'ultimate-push-notifications' ),
				'desc_tip' => __( 'Please set the following configuration to get message related notifications.', 'ultimate-push-notifications' ),
			),
			'cs_set_notifications[on_groups_invitation]' => array(
				'title'    =>  __( 'Groups Invitation ', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when you receive a group invitation. You can change the notification message with your own words.You can use: {sender_full_name}, {sender_first_name}, {sender_last_name}, {sender_display_name}, {group_name}, {inviter_name}, {message} ', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpGroupsInvitationCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpGroupsInvitationCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpGroupsInvitationTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpGroupsInvitationTitle', $option, 'New Group Invitation Received!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpGroupsInvitationBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpGroupsInvitationBody', $option, '{sender_full_name}, send you a invitation to join {group_name} on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),
			'cs_set_notifications[on_group_details_updated]' => array(
				'title'    =>  __( 'Group Details Update', 'ultimate-push-notifications' ),
				'type'     => 'miscellaneous',
				'desc_tip' => __( 'Please check the checkbox to get notification when you receive a group invitation. You can change the notification message with your own words.You can use: {group_name}, {changed_text} ', 'ultimate-push-notifications' ),
				
				'options'  => array(
					'cs_set_notifications[bpGroupDetailsUpdatedCheck]' => array(
						'type'        => 'checkbox',
						'class'       => '',
						'value'       => FormBuilder::get_value( 'bpGroupDetailsUpdatedCheck', $option, '' ),
						'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
					),
					'cs_set_notifications[bpGroupDetailsUpdatedTitle]' => array(
						'type'              => 'text',
						'class'             => 'form-control notification-title',
						'value'             => FormBuilder::get_value( 'bpGroupDetailsUpdatedTitle', $option, 'Group Details Updated!' ),
						'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 38,
						),
					),
					'cs_set_notifications[bpGroupDetailsUpdatedBody]' => array(
						'type'              => 'textarea',
						'class'             => 'form-control mt-10',
						'value'             => FormBuilder::get_value( 'bpGroupDetailsUpdatedBody', $option, '{group_name}, has been updated their details on BuddyPress.' ),
						'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
						'custom_attributes' => array(
							'maxlength' => 174,
						),
					),

				),
			),



		);
	}


	/**
	 * Contact Form 7 Fields
	 *
	 * @return void
	 */
	public static function upn_wpcf7_fields( $option = array() ) {
		if ( is_super_admin() ) { 
			return array(
				'st1'                                     => array(
					'type'     => 'section_title',
					'title'    => __( 'Contact Form 7 Notifications', 'ultimate-push-notifications' ),
					'desc_tip' => __( 'Please set the following configuration to get contact form 7 form related notification.', 'ultimate-push-notifications' ),
				),
				'cs_set_notifications[on_wpcf7_form_submission]' => array(
					'title'    => __( 'New Query', 'ultimate-push-notifications' ),
					'type'     => 'miscellaneous',
					'desc_tip' => __( 'Please check the checkbox to get notification when someone send message via contact form 7. You can change the notification message with your own words.You can use: {user_name}, {user_email}, {msg_subject}, {msg_body}', 'ultimate-push-notifications' ),
					'options'  => array(
						'cs_set_notifications[wpcf7FormSubmissionCheck]' => array(
							'type'        => 'checkbox',
							'class'       => '',
							'value'       => FormBuilder::get_value( 'wpcf7FormSubmissionCheck', $option, '' ),
							'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						),
						'cs_set_notifications[wpcf7FormSubmissionTitle]' => array(
							'type'              => 'text',
							'class'             => 'form-control notification-title',
							'value'             => FormBuilder::get_value( 'wpcf7FormSubmissionTitle', $option, 'New Form Submission!' ),
							'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 38,
							),
						),
						'cs_set_notifications[wpcf7FormSubmissionBody]' => array(
							'type'              => 'textarea',
							'class'             => 'form-control mt-10',
							'value'             => FormBuilder::get_value( 'wpcf7FormSubmissionBody', $option, '{user_name}, submitted new query via contact form 7.' ),
							'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 174,
							),
						),
	
					),
				),
			);
		}
		return false;
	}

	/**
	 * The Events Calendar
	 *
	 * @return void
	 */
	public static function upn_the_events_calendar_fields( $option = array() ) {
			return array(
				'st1'                                     => array(
					'type'     => 'section_title',
					'title'    => __( 'The Events Calendar', 'ultimate-push-notifications' ),
					'desc_tip' => __( 'Please set the following configuration to get events related notifications.', 'ultimate-push-notifications' ),
				),
				'cs_set_notifications[on_before_events]' => array(
					'title'    => __( 'New Event', 'ultimate-push-notifications' ),
					'type'     => 'miscellaneous',
					'desc_tip' => __( 'Please check the checkbox to get notification before the events happening. You can change the notification message with your own words.You can use: {event_name}', 'ultimate-push-notifications' ),
					'options'  => array(
						'cs_set_notifications[theEventsCalendarEventsCheck]' => array(
							'type'        => 'checkbox',
							'class'       => '',
							'value'       => FormBuilder::get_value( 'theEventsCalendarEventsCheck', $option, '' ),
							'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						),
						'cs_set_notifications[theEventsCalendarEventsTitle]' => array(
							'type'              => 'text',
							'class'             => 'form-control notification-title',
							'value'             => FormBuilder::get_value( 'theEventsCalendarEventsTitle', $option, 'New Events!' ),
							'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 38,
							),
						),
						'cs_set_notifications[theEventsCalendarEventsBody]' => array(
							'type'              => 'textarea',
							'class'             => 'form-control mt-10',
							'value'             => FormBuilder::get_value( 'theEventsCalendarEventsBody', $option, '{event_name}, Happening today.' ),
							'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 174,
							),
						),
	
					),
				),
				'cs_set_notifications[on_event_cancel]' => array(
					'title'    => __( 'Event Cancelled', 'ultimate-push-notifications' ),
					'type'     => 'miscellaneous',
					'desc_tip' => __( 'Please check the checkbox to get notification when the event get cancelled. You can change the notification message with your own words.You can use: {event_name}, {reason}', 'ultimate-push-notifications' ),
					'options'  => array(
						'cs_set_notifications[theEventsCalendarEventCancelledCheck]' => array(
							'type'        => 'checkbox',
							'class'       => '',
							'value'       => FormBuilder::get_value( 'theEventsCalendarEventCancelledCheck', $option, '' ),
							'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						),
						'cs_set_notifications[theEventsCalendarEventCancelledTitle]' => array(
							'type'              => 'text',
							'class'             => 'form-control notification-title',
							'value'             => FormBuilder::get_value( 'theEventsCalendarEventCancelledTitle', $option, 'Event Cancelled!' ),
							'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 38,
							),
						),
						'cs_set_notifications[theEventsCalendarEventCancelledBody]' => array(
							'type'              => 'textarea',
							'class'             => 'form-control mt-10',
							'value'             => FormBuilder::get_value( 'theEventsCalendarEventCancelledBody', $option, '{event_name}, has been cancelled.' ),
							'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 174,
							),
						),
	
					),
				),
				'cs_set_notifications[on_event_postponed]' => array(
					'title'    => __( 'Event Postponed', 'ultimate-push-notifications' ),
					'type'     => 'miscellaneous',
					'desc_tip' => __( 'Please check the checkbox to get notification when the event get postponed. You can change the notification message with your own words.You can use: {event_name}, {reason}', 'ultimate-push-notifications' ),
					'options'  => array(
						'cs_set_notifications[theEventsCalendarEventPostponedCheck]' => array(
							'type'        => 'checkbox',
							'class'       => '',
							'value'       => FormBuilder::get_value( 'theEventsCalendarEventPostponedCheck', $option, '' ),
							'placeholder' => __( 'Enter discount amount', 'ultimate-push-notifications' ),
						),
						'cs_set_notifications[theEventsCalendarEventPostponedTitle]' => array(
							'type'              => 'text',
							'class'             => 'form-control notification-title',
							'value'             => FormBuilder::get_value( 'theEventsCalendarEventPostponedTitle', $option, 'Event postponed!' ),
							'placeholder'       => __( 'Enter notification title. Maxlength : 38', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 38,
							),
						),
						'cs_set_notifications[theEventsCalendarEventPostponedBody]' => array(
							'type'              => 'textarea',
							'class'             => 'form-control mt-10',
							'value'             => FormBuilder::get_value( 'theEventsCalendarEventPostponedBody', $option, '{event_name}, has postponed.' ),
							'placeholder'       => __( 'Enter notification description. Maxlength : 174', 'ultimate-push-notifications' ),
							'custom_attributes' => array(
								'maxlength' => 174,
							),
						),
	
					),
				),
			);
	}

}

