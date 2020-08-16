<?php namespace UltimatePushNotifications\admin\functions\notifications;

/**
 * WooCommerce Notification Builders
 *
 * @package Notifications
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\admin\functions\SendNotifications;
use UltimatePushNotifications\admin\options\functions\AppConfig;
use UltimatePushNotifications\admin\options\functions\SetNotifications;

class Upn_WooNotifications{

    /**
     * Build & send notification 
     * when purchase completed
     *
     * @return void
     */
    public static function build_notific_on_payment_complete( $order_id ){
        $order = wc_get_order( $order_id );

        if( empty( $order ) ){
            return;
        }

        $items = $order->get_items();
        $authors = [];
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $post   = \get_post( $product_id );
            if( isset( $authors[ 'author_' . $post->post_author] ) ){
                $old_data = $authors[ 'author_' . $post->post_author];
                $authors[ 'author_' . $post->post_author] = array(
                    'author_id' => $post->post_author,
                    'total_sold'=> $item->get_total() + $old_data[ 'total_sold' ]
                );

            }else{
                $authors += array(
                    'author_' . $post->post_author => array(
                        'author_id' => $post->post_author,
                        'total_sold'=> $item->get_total() 
                    )
                );
            }

        }

        $first_name = $order->data['billing']['first_name'];
        $last_name = $order->data['billing']['last_name'];
        $total = $order->data['total'] . ' ' .$order->data['currency'];
        $full_name = $first_name . ' ' . $last_name;

        $currency_symbol = \get_woocommerce_currency_symbol();
        $find = array(
            '{first_name}', '{last_name}', '{full_name}', '{total}'
        );
        $icon = CS_UPN_PLUGIN_ASSET_URI . '/img/icon-product-sold.png';

        if( $authors ){
            foreach($authors as $author ){
                $hasUserAsked = SetNotifications::has_user_asked_for_notification( $author['author_id'], 'productSold' );
                if( $hasUserAsked ){
                    $response[] = SendNotifications::prepare_send_notifications( (array) $hasUserAsked + array(
                        'find' => $find,
                        'replace' => array( $first_name, $last_name, $full_name, $currency_symbol.$author['total_sold'] ),
                        'icon' => $icon,
                        'click_action' =>$order->get_view_order_url()
                    ) );

                }

            }
        }

        return $response;
    }

    /**
     * Build & send notification 
     * when product added to cart
     *
     * @return void
     */
    public function build_notific_on_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ){
        global $woocommerce;

        $items = $woocommerce->cart->get_cart();

        $product = wc_get_product( $product_id );
        $image_id  = $product->get_image_id();
        $image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );

        $price = get_woocommerce_currency_symbol() . $product->get_price();

        $post   = \get_post( $product_id );
        $hasUserAsked = SetNotifications::has_user_asked_for_notification( $post->post_author, 'addToCart' );        
        if( $hasUserAsked ){
            $find = array(
                '{product_title}', '{price}'
            );
            $replace = array(
                $product->get_title(), $price
            );
            $icon = CS_UPN_PLUGIN_ASSET_URI . '/img/icon-product-added-to-cart.png';
            $response = SendNotifications::prepare_send_notifications( (array) $hasUserAsked + array(
                'find' => $find,
                'replace' => $replace,
                'icon' => $icon,
                'click_action' => get_permalink( $product_id )
            ) );

            return $response;
        }

        return false;
    }
    
    
    public function build_notific_on_order_status(){

    }

}