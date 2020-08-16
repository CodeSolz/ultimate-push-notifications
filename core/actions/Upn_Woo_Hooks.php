<?php namespace UltimatePushNotifications\actions;

/**
 * Class: Register custom menu
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\functions\notifications\Upn_WooNotifications;

if ( ! \class_exists( 'Upn_Woo_Hooks' ) ) {

	class Upn_Woo_Hooks {



		function __construct() {
			/**Send notification when product purchase complete */
			add_action( 'woocommerce_payment_complete', array( $this, 'upn_woocommerce_payment_complete' ) );
			
			/**Send notification when a product added to cart */
			add_action( 'woocommerce_add_to_cart', array( $this, 'upn_woocommerce_add_to_cart' ), 10, 6 );
			
			/**Send notification when order change */
			add_action( 'woocommerce_order_status_changed', array( $this, 'upn_update_order_status' ), 10, 4 );

			//test
			add_action( 'init', array( $this, 'upn_woocommerce_payment_complete' ) );
		}

		/**
		 * Send notification when 
		 * someone purchase a product
		 *
		 * @return void
		 */
		public function upn_woocommerce_payment_complete( $order_id ){
			return Upn_WooNotifications::build_notific_on_payment_complete( $order_id );
		}
		
		/**
		 * Send notification when 
		 * product added to cart
		 *
		 * @return void
		 */
		public function upn_woocommerce_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ){
			return Upn_WooNotifications::build_notific_on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data  );
		}

		public function upn_update_order_status(){

		}

		
	}

}
