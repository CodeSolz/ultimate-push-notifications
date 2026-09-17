<?php namespace UltimatePushNotifications\automation\triggers;

use UltimatePushNotifications\automation\Event;

/**
 * WooCommerce triggers.
 *
 * The audience is explicit on every rule — store staff, the product's
 * author (vendor), or the buyer — instead of the old hard-wired "product
 * author", which on a multi-author store meant the owner got nothing.
 *
 * @package Automation
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class WooCommerceTriggers {

	/**
	 * @return array[]
	 */
	public static function definitions() {
		return array(
			self::order_placed(),
			self::order_status_changed(),
			self::payment_complete(),
			self::add_to_cart(),
			self::low_stock(),
			self::no_stock(),
			self::back_in_stock(),
			self::price_drop(),
		);
	}

	/**
	 * @return bool
	 */
	public static function available() {
		return \function_exists( 'wc_get_order' );
	}

	/**
	 * The order tags every order trigger shares.
	 *
	 * @return array
	 */
	private static function order_tags() {
		return array(
			'order_id'        => \__( 'Order number', 'ultimate-push-notifications' ),
			'order_total'     => \__( 'Order total', 'ultimate-push-notifications' ),
			'order_status'    => \__( 'Order status', 'ultimate-push-notifications' ),
			'customer_name'   => \__( 'Customer name', 'ultimate-push-notifications' ),
			'customer_email'  => \__( 'Customer email', 'ultimate-push-notifications' ),
			'items_count'     => \__( 'Number of items', 'ultimate-push-notifications' ),
			'item_names'      => \__( 'Item names', 'ultimate-push-notifications' ),
			'payment_method'  => \__( 'Payment method', 'ultimate-push-notifications' ),
			'order_url'       => \__( 'Order link (customer)', 'ultimate-push-notifications' ),
			'order_admin_url' => \__( 'Order link (admin)', 'ultimate-push-notifications' ),
		);
	}

	/**
	 * @return array
	 */
	private static function order_people() {
		return array(
			'buyer'           => \__( 'The customer', 'ultimate-push-notifications' ),
			'product_authors' => \__( 'The products\' authors (vendors)', 'ultimate-push-notifications' ),
		);
	}

	/**
	 * @return array value => label of every order status without the wc- prefix.
	 */
	public static function status_options() {
		if ( ! \function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		$out = array();
		foreach ( \wc_get_order_statuses() as $key => $label ) {
			$out[ \preg_replace( '/^wc-/', '', $key ) ] = $label;
		}
		return $out;
	}

	/**
	 * Build the Event every order trigger shares.
	 *
	 * @param string $trigger
	 * @param mixed  $order_or_id
	 * @param string $key
	 * @param array  $params
	 * @return Event|null
	 */
	private static function order_event( $trigger, $order_or_id, $key, array $params = array() ) {
		$order = \is_object( $order_or_id ) ? $order_or_id : \wc_get_order( (int) $order_or_id );
		if ( ! $order || ! \is_callable( array( $order, 'get_id' ) ) ) {
			return null;
		}

		$authors = array();
		$names   = array();
		foreach ( (array) $order->get_items() as $item ) {
			$names[] = $item->get_name();
			$post    = \get_post( (int) $item->get_product_id() );
			if ( $post && (int) $post->post_author > 0 ) {
				$authors[] = (int) $post->post_author;
			}
		}
		$shown = \array_slice( $names, 0, 3 );
		if ( \count( $names ) > 3 ) {
			$shown[] = \sprintf( \__( '+%d more', 'ultimate-push-notifications' ), \count( $names ) - 3 );
		}

		$status   = $order->get_status();
		$customer = (int) $order->get_customer_id();

		$event = new Event( $trigger, array(
			'key'     => $key,
			'actor'   => Helpers::actor(),
			'params'  => \array_merge( array( 'status' => $status, 'payment_method' => (string) $order->get_payment_method() ), $params ),
			'people'  => array(
				'buyer'           => $customer > 0 ? array( $customer ) : array(),
				'product_authors' => \array_values( \array_unique( $authors ) ),
			),
			'context' => array( 'user' => Helpers::user( $customer ) ),
			'url'     => (string) $order->get_edit_order_url(),
		) );

		$event->extra( 'order_id', (string) $order->get_order_number() );
		$event->extra( 'order_total', Helpers::money( $order->get_total(), $order->get_currency() ) );
		$event->extra( 'order_status', \function_exists( 'wc_get_order_status_name' ) ? \wc_get_order_status_name( $status ) : $status );
		$event->extra( 'customer_name', \trim( $order->get_formatted_billing_full_name() ) );
		$event->extra( 'customer_email', (string) $order->get_billing_email() );
		$event->extra( 'items_count', (string) $order->get_item_count() );
		$event->extra( 'item_names', \implode( ', ', $shown ) );
		$event->extra( 'payment_method', (string) $order->get_payment_method_title() );
		$event->extra( 'order_url', (string) $order->get_view_order_url() );
		$event->extra( 'order_admin_url', $event->url );

		return $event;
	}

	private static function order_placed() {
		return array(
			'key'         => 'woo.order_placed',
			'label'       => \__( 'New order', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A customer completes checkout.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_checkout_order_processed' => 3, 'woocommerce_store_api_checkout_order_processed' => 1 ),
			'params'      => array(
				'payment_method' => array(
					'label'   => \__( 'Paid with', 'ultimate-push-notifications' ),
					'options' => function () {
						if ( ! \function_exists( 'WC' ) ) {
							return array();
						}
						$out = array();
						foreach ( (array) \WC()->payment_gateways()->payment_gateways() as $g ) {
							$out[ $g->id ] = $g->get_title();
						}
						return $out;
					},
				),
			),
			'people'      => self::order_people(),
			'tags'        => self::order_tags(),
			'defaults'    => array(
				'audience' => 'roles',
				'roles'    => array( 'administrator', 'shop_manager' ),
				'title'    => \__( 'New order #{order_id} — {order_total}', 'ultimate-push-notifications' ),
				'body'     => \__( '{customer_name}: {item_names}', 'ultimate-push-notifications' ),
				'url'      => '{order_admin_url}',
			),
			'build'       => function ( $args ) {
				// Classic checkout passes (order_id, posted, order); the block checkout passes the order.
				$order = isset( $args[2] ) && \is_object( $args[2] ) ? $args[2] : ( isset( $args[0] ) ? $args[0] : null );
				$id    = \is_object( $order ) ? $order->get_id() : (int) $order;
				return self::order_event( 'woo.order_placed', $order, 'order_placed:' . $id );
			},
		);
	}

	private static function order_status_changed() {
		return array(
			'key'         => 'woo.order_status_changed',
			'label'       => \__( 'Order status changes', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'An order moves to a new status.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_order_status_changed' => 4 ),
			'params'      => array(
				'status_to'   => array( 'label' => \__( 'New status', 'ultimate-push-notifications' ), 'options' => array( __CLASS__, 'status_options' ) ),
				'status_from' => array( 'label' => \__( 'Previous status', 'ultimate-push-notifications' ), 'options' => array( __CLASS__, 'status_options' ) ),
			),
			'people'      => self::order_people(),
			'tags'        => self::order_tags() + array( 'status_from' => \__( 'Previous status', 'ultimate-push-notifications' ) ),
			'defaults'    => array(
				'audience' => 'buyer',
				'title'    => \__( 'Order #{order_id} is now {order_status}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Tap to view your order.', 'ultimate-push-notifications' ),
				'url'      => '{order_url}',
			),
			'build'       => function ( $args ) {
				$id   = isset( $args[0] ) ? (int) $args[0] : 0;
				$from = isset( $args[1] ) ? (string) $args[1] : '';
				$to   = isset( $args[2] ) ? (string) $args[2] : '';
				$event = self::order_event( 'woo.order_status_changed', isset( $args[3] ) && \is_object( $args[3] ) ? $args[3] : $id, 'order_status:' . $id . ':' . $to, array( 'status_to' => $to, 'status_from' => $from ) );
				if ( $event ) {
					$event->extra( 'status_from', \function_exists( 'wc_get_order_status_name' ) ? \wc_get_order_status_name( $from ) : $from );
				}
				return $event;
			},
		);
	}

	private static function payment_complete() {
		return array(
			'key'         => 'woo.payment_complete',
			'label'       => \__( 'Payment received', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A payment gateway confirms payment for an order.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_payment_complete' => 1 ),
			'people'      => self::order_people(),
			'tags'        => self::order_tags(),
			'defaults'    => array(
				'audience' => 'product_authors',
				'title'    => \__( 'You made a sale: {order_total}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Order #{order_id} from {customer_name} is paid.', 'ultimate-push-notifications' ),
				'url'      => '{order_admin_url}',
			),
			'build'       => function ( $args ) {
				$id = isset( $args[0] ) ? (int) $args[0] : 0;
				return self::order_event( 'woo.payment_complete', $id, 'payment_complete:' . $id );
			},
		);
	}

	private static function add_to_cart() {
		return array(
			'key'         => 'woo.add_to_cart',
			'label'       => \__( 'Product added to cart', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A shopper adds a product to their cart.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_add_to_cart' => 6 ),
			'people'      => array(
				'product_author' => \__( 'The product\'s author (vendor)', 'ultimate-push-notifications' ),
				'shopper'        => \__( 'The shopper (if logged in)', 'ultimate-push-notifications' ),
			),
			'tags'        => array(
				'product_title' => \__( 'Product name', 'ultimate-push-notifications' ),
				'product_price' => \__( 'Price', 'ultimate-push-notifications' ),
				'product_url'   => \__( 'Product link', 'ultimate-push-notifications' ),
				'quantity'      => \__( 'Quantity', 'ultimate-push-notifications' ),
				'shopper_name'  => \__( 'Shopper name', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'product_author',
				'title'    => \__( '{product_title} added to a cart', 'ultimate-push-notifications' ),
				'body'     => \__( '{quantity} × {product_price}', 'ultimate-push-notifications' ),
				'url'      => '{product_url}',
			),
			'build'       => function ( $args ) {
				list( $cart_item_key, $product_id, $quantity, $variation_id ) = \array_pad( $args, 4, 0 );
				$product = \wc_get_product( (int) $variation_id > 0 ? (int) $variation_id : (int) $product_id );
				if ( ! $product ) {
					return null;
				}
				$post  = \get_post( (int) $product_id );
				$actor = Helpers::actor();

				$event = new Event( 'woo.add_to_cart', array(
					'key'     => 'add_to_cart:' . $cart_item_key,
					'actor'   => $actor,
					'people'  => array(
						'product_author' => $post ? array( (int) $post->post_author ) : array(),
						'shopper'        => $actor > 0 ? array( $actor ) : array(),
					),
					'context' => array( 'post' => $post, 'user' => Helpers::user( $actor ) ),
					'url'     => (string) $product->get_permalink(),
					'image'   => $product->get_image_id() ? (string) \wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) : '',
				) );
				$event->extra( 'product_title', $product->get_name() );
				$event->extra( 'product_price', Helpers::money( $product->get_price() ) );
				$event->extra( 'product_url', $event->url );
				$event->extra( 'quantity', (string) (int) $quantity );
				$event->extra( 'shopper_name', $actor > 0 ? Helpers::user_name( $actor ) : \__( 'A visitor', 'ultimate-push-notifications' ) );
				return $event;
			},
		);
	}

	/** @var array<int,float> product id => price in the database before the save in progress */
	private static $prices_before = array();

	/**
	 * A product's price goes down. The price before the save is read from
	 * the database just before it is written, so a drop is a real drop.
	 */
	private static function price_drop() {
		return array(
			'key'         => 'woo.price_drop',
			'next'        => array( 'key' => 'commerce', 'label' => \__( 'Per-shopper price alerts', 'ultimate-push-notifications' ), 'text' => \__( 'let each shopper ask for their own alert on the product page, sent only to them when the price they saw drops — Store Automations.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Price drop', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A product is saved with a lower price than before — a sale starts, or the price is cut.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_before_product_object_save' => 1, 'woocommerce_product_object_updated_props' => 2 ),
			'params'      => array(
				'drop' => array(
					'label'   => \__( 'Drop of at least', 'ultimate-push-notifications' ),
					'options' => array( '5' => '5%', '10' => '10%', '20' => '20%', '30' => '30%', '50' => '50%' ),
				),
			),
			'people'      => array( 'product_author' => \__( 'The product\'s author (vendor)', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'product_title' => \__( 'Product name', 'ultimate-push-notifications' ),
				'price'         => \__( 'New price', 'ultimate-push-notifications' ),
				'old_price'     => \__( 'Old price', 'ultimate-push-notifications' ),
				'drop'          => \__( 'Drop (%)', 'ultimate-push-notifications' ),
				'product_url'   => \__( 'Product link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'everyone',
				'title'    => \__( 'Price drop: {product_title}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Now {price}, was {old_price} — {drop}% off.', 'ultimate-push-notifications' ),
				'url'      => '{product_url}',
			),
			'build'       => function ( $args ) {
				$product = isset( $args[0] ) ? $args[0] : null;
				if ( ! \is_object( $product ) || ! \is_callable( array( $product, 'get_id' ) ) ) {
					return null;
				}
				$id = (int) $product->get_id();
				if ( ! isset( $args[1] ) || ! \is_array( $args[1] ) ) {
					// Before the save: remember what the shop currently charges.
					$stored = $id > 0 && \function_exists( 'wc_get_product' ) ? \wc_get_product( $id ) : null;
					self::$prices_before[ $id ] = $stored && \is_callable( array( $stored, 'get_price' ) ) ? (float) $stored->get_price( 'edit' ) : 0.0;
					return null;
				}
				if ( ! \array_intersect( array( 'price', 'sale_price', 'regular_price', 'date_on_sale_from', 'date_on_sale_to' ), (array) $args[1] ) ) {
					return null;
				}
				$was = isset( self::$prices_before[ $id ] ) ? self::$prices_before[ $id ] : 0.0;
				unset( self::$prices_before[ $id ] );
				$now = (float) $product->get_price( 'edit' );
				if ( $was <= 0 || $now <= 0 || $now >= $was ) {
					return null;
				}
				$drop = (int) \round( 100 * ( $was - $now ) / $was );
				$post = \get_post( $id );

				$event = new Event( 'woo.price_drop', array(
					'key'     => 'price_drop:' . $id . ':' . \number_format( $now, 2, '.', '' ),
					'actor'   => 0,
					'params'  => array( 'drop' => (string) self::drop_bucket( $drop ) ),
					'people'  => array( 'product_author' => $post ? array( (int) $post->post_author ) : array() ),
					'context' => array( 'post' => $post ),
					'url'     => (string) $product->get_permalink(),
				) );
				$event->extra( 'product_title', $product->get_name() );
				$event->extra( 'price', Helpers::money( $now ) );
				$event->extra( 'old_price', Helpers::money( $was ) );
				$event->extra( 'drop', (string) $drop );
				$event->extra( 'product_url', $event->url );
				if ( \function_exists( 'get_the_post_thumbnail_url' ) ) {
					$event->image = (string) \get_the_post_thumbnail_url( $id, 'large' );
				}
				return $event;
			},
		);
	}

	/**
	 * The largest listed threshold the drop clears, so a rule for "at least
	 * 10%" matches a 12% cut.
	 *
	 * @param int $drop
	 * @return int
	 */
	public static function drop_bucket( $drop ) {
		$bucket = 0;
		foreach ( array( 5, 10, 20, 30, 50 ) as $t ) {
			if ( $drop >= $t ) {
				$bucket = $t;
			}
		}
		return $bucket;
	}

	/**
	 * A product that was out of stock is in stock again.
	 */
	private static function back_in_stock() {
		return array(
			'key'         => 'woo.back_in_stock',
			'next'        => array( 'key' => 'commerce', 'label' => \__( 'Per-shopper stock alerts', 'ultimate-push-notifications' ), 'text' => \__( 'a \"tell me when it is back\" button on the product page, and only the people who pressed it hear — Store Automations.', 'ultimate-push-notifications' ) ),
			'label'       => \__( 'Back in stock', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A product\'s stock status turns to "in stock".', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_product_set_stock_status' => 3, 'woocommerce_variation_set_stock_status' => 3 ),
			'people'      => array( 'product_author' => \__( 'The product\'s author (vendor)', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'product_title' => \__( 'Product name', 'ultimate-push-notifications' ),
				'price'         => \__( 'Price', 'ultimate-push-notifications' ),
				'product_url'   => \__( 'Product link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'everyone',
				'title'    => \__( 'Back in stock: {product_title}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Now {price}. It went quickly last time.', 'ultimate-push-notifications' ),
				'url'      => '{product_url}',
			),
			'build'       => function ( $args ) {
				list( $product_id, $status, $product ) = \array_pad( $args, 3, null );
				if ( 'instock' !== (string) $status || ! \is_object( $product ) || ! \is_callable( array( $product, 'get_id' ) ) ) {
					return null;
				}
				// A variation announces its parent: that is the page people know.
				if ( \is_callable( array( $product, 'get_parent_id' ) ) && (int) $product->get_parent_id() > 0 && \function_exists( 'wc_get_product' ) ) {
					$parent = \wc_get_product( (int) $product->get_parent_id() );
					if ( $parent ) {
						$product = $parent;
					}
				}
				$id   = (int) $product->get_id();
				$post = \get_post( $id );

				$event = new Event( 'woo.back_in_stock', array(
					'key'     => 'back_in_stock:' . $id . ':' . \gmdate( 'Y-m-d' ),
					'actor'   => 0,
					'people'  => array( 'product_author' => $post ? array( (int) $post->post_author ) : array() ),
					'context' => array( 'post' => $post ),
					'url'     => (string) $product->get_permalink(),
				) );
				$event->extra( 'product_title', $product->get_name() );
				$event->extra( 'price', Helpers::money( (float) $product->get_price( 'edit' ) ) );
				$event->extra( 'product_url', $event->url );
				if ( \function_exists( 'get_the_post_thumbnail_url' ) ) {
					$event->image = (string) \get_the_post_thumbnail_url( $id, 'large' );
				}
				return $event;
			},
		);
	}

	/**
	 * @param string $trigger
	 * @param object $product
	 * @return Event|null
	 */
	private static function stock_event( $trigger, $product ) {
		if ( ! \is_object( $product ) || ! \is_callable( array( $product, 'get_id' ) ) ) {
			return null;
		}
		$post = \get_post( (int) $product->get_id() );
		$qty  = $product->get_stock_quantity();

		$event = new Event( $trigger, array(
			'key'     => $trigger . ':' . $product->get_id() . ':' . (int) $qty,
			'actor'   => 0,
			'people'  => array( 'product_author' => $post ? array( (int) $post->post_author ) : array() ),
			'context' => array( 'post' => $post ),
			'url'     => \admin_url( 'post.php?post=' . (int) $product->get_id() . '&action=edit' ),
		) );
		$event->extra( 'product_title', $product->get_name() );
		$event->extra( 'stock_quantity', null === $qty ? '0' : (string) (int) $qty );
		$event->extra( 'product_url', (string) $product->get_permalink() );
		$event->extra( 'product_admin_url', $event->url );
		return $event;
	}

	private static function low_stock() {
		return array(
			'key'         => 'woo.low_stock',
			'label'       => \__( 'Stock is low', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A product reaches its low-stock threshold.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_low_stock' => 1 ),
			'people'      => array( 'product_author' => \__( 'The product\'s author (vendor)', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'product_title'     => \__( 'Product name', 'ultimate-push-notifications' ),
				'stock_quantity'    => \__( 'Units left', 'ultimate-push-notifications' ),
				'product_url'       => \__( 'Product link', 'ultimate-push-notifications' ),
				'product_admin_url' => \__( 'Edit product link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'roles',
				'roles'    => array( 'administrator', 'shop_manager' ),
				'title'    => \__( 'Low stock: {product_title}', 'ultimate-push-notifications' ),
				'body'     => \__( '{stock_quantity} left.', 'ultimate-push-notifications' ),
				'url'      => '{product_admin_url}',
			),
			'build'       => function ( $args ) {
				return self::stock_event( 'woo.low_stock', isset( $args[0] ) ? $args[0] : null );
			},
		);
	}

	private static function no_stock() {
		return array(
			'key'         => 'woo.no_stock',
			'label'       => \__( 'Out of stock', 'ultimate-push-notifications' ),
			'group'       => 'WooCommerce',
			'description' => \__( 'A product sells out.', 'ultimate-push-notifications' ),
			'available'   => array( __CLASS__, 'available' ),
			'hooks'       => array( 'woocommerce_no_stock' => 1 ),
			'people'      => array( 'product_author' => \__( 'The product\'s author (vendor)', 'ultimate-push-notifications' ) ),
			'tags'        => array(
				'product_title'     => \__( 'Product name', 'ultimate-push-notifications' ),
				'product_url'       => \__( 'Product link', 'ultimate-push-notifications' ),
				'product_admin_url' => \__( 'Edit product link', 'ultimate-push-notifications' ),
			),
			'defaults'    => array(
				'audience' => 'roles',
				'roles'    => array( 'administrator', 'shop_manager' ),
				'title'    => \__( 'Sold out: {product_title}', 'ultimate-push-notifications' ),
				'body'     => \__( 'Restock or hide the product.', 'ultimate-push-notifications' ),
				'url'      => '{product_admin_url}',
			),
			'build'       => function ( $args ) {
				return self::stock_event( 'woo.no_stock', isset( $args[0] ) ? $args[0] : null );
			},
		);
	}

}
