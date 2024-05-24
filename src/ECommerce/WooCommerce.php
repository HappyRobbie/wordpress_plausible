<?php
/**
 * Plausible Analytics | ECommerce | WooCommerce.
 *
 * @since      2.1.0
 * @package    WordPress
 * @subpackage Plausible Analytics
 */

namespace Plausible\Analytics\WP\ECommerce;

use Plausible\Analytics\WP\ECommerce;
use Plausible\Analytics\WP\Proxy;
use WC_Cart;

class WooCommerce {
	const PURCHASE_TRACKED_META_KEY = '_plausible_analytics_purchase_tracked';

	const CUSTOM_PROPERTIES         = [
		'id',
		'order_id',
		'name',
		'price',
		'product_id',
		'variation_id',
		'quantity',
		'tax_class',
		'subtotal',
		'subtotal_tax',
		'total',
		'total_tax',
	];

	/**
	 * Build class.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Filter and action hooks.
	 *
	 * @return void
	 */
	private function init() {
		add_action( 'woocommerce_add_to_cart', [ $this, 'track_add_to_cart' ], 10, 4 );
		add_action( 'woocommerce_remove_cart_item', [ $this, 'track_remove_cart_item' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'track_entered_checkout' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'track_purchase' ] );
	}

	/**
	 * @param string $item_cart_id ID of the item in the cart.
	 * @param string $product_id   ID of the product added to the cart.
	 * @param        $quantity
	 * @param        $variation_id
	 *
	 * @return void
	 */
	public function track_add_to_cart( $item_cart_id, $product_id, $quantity, $variation_id ) {
		$product       = wc_get_product( $product_id );
		$product_data  = $this->clean_data( $product->get_data() );
		$cart          = wc()->cart;
		$cart_contents = $cart->get_cart_contents();

		foreach ( $cart_contents as &$cart_item ) {
			$cart_item = $this->clean_data( $cart_item );
		}

		$added_to_cart = [
			'quantity'     => $quantity,
			'variation_id' => $variation_id,
		];
		$props         = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_add_to_cart_custom_properties',
			[
				'props' => [
					'item' => array_merge( $added_to_cart, $product_data ),
					'cart' => $cart_contents,
				],
			]
		);
		$event_label   = __( 'Add Item To Cart', 'plausible-analytics' );
		$proxy         = new Proxy( false );

		$proxy->do_request( wp_get_referer(), $event_label, $props );
	}

	/**
	 * Removes unneeded elements from the array.
	 *
	 * @param array $product Product Data.
	 *
	 * @return mixed
	 */
	private function clean_data( $product ) {
		foreach ( $product as $key => $value ) {
			if ( ! in_array( $key, self::CUSTOM_PROPERTIES ) ) {
				unset( $product[ $key ] );
			}
		}

		return $product;
	}

	/**
	 * Track Remove from cart events.
	 *
	 * @param string  $cart_item_key Key of item being removed from cart.
	 * @param WC_Cart $cart          Instance of the current cart.
	 *
	 * @return void
	 */
	public function track_remove_cart_item( $cart_item_key, $cart ) {
		$cart_contents          = $cart->get_cart_contents();
		$item_removed_from_cart = $this->clean_data( $cart_contents[ $cart_item_key ] ?? [] );

		unset( $cart_contents[ $cart_item_key ] );

		foreach ( $cart_contents as &$item_in_cart ) {
			$item_in_cart = $this->clean_data( $item_in_cart );
		}

		$props       = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_remove_cart_item_custom_properties',
			[
				'props' => [
					'removed_item' => $item_removed_from_cart,
					'cart'         => $cart_contents,
				],
			]
		);
		$event_label = __( 'Remove Cart Item', 'plausible-analytics' );
		$proxy       = new Proxy( false );

		$proxy->do_request( wp_get_referer(), $event_label, $props );
	}

	/**
	 * @return void
	 */
	public function track_entered_checkout() {
		if ( ! is_checkout() ) {
			return;
		}

		$cart          = WC()->cart;
		$cart_contents = $cart->get_cart_contents();

		foreach ( $cart_contents as &$cart_item ) {
			$cart_item = $this->clean_data( $cart_item );
		}

		$props       = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_entered_checkout_custom_properties',
			[
				'cart' => $cart_contents,
			]
		);
		$props       = wp_json_encode( $props );
		$event_label = __( 'Entered Checkout', 'plausible-analytics' );

		echo sprintf( ECommerce::SCRIPT_WRAPPER, "window.plausible( '$event_label', $props )" );
	}

	/**
	 * Track WooCommerce purchase on thank you page.
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function track_purchase( $order_id ) {
		$order      = wc_get_order( $order_id );
		$is_tracked = $order->get_meta( self::PURCHASE_TRACKED_META_KEY );

		if ( $is_tracked ) {
			return;
		}

		$items       = $this->get_items( $order );
		$props       = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_purchase_custom_properties',
			[
				'transaction_id' => $order->get_transaction_id(),
				'items'          => $items,
			]
		);
		$props       = wp_json_encode(
			[
				'revenue' => [ 'amount' => number_format_i18n( $order->get_total(), 2 ), 'currency' => $order->get_currency() ],
				'props'   => $props,
			]
		);
		$event_label = __( 'Purchase', 'plausible-analytics' );

		echo sprintf( ECommerce::SCRIPT_WRAPPER, "window.plausible( '$event_label', $props )" );

		$order->add_meta_data( self::PURCHASE_TRACKED_META_KEY, true );
		$order->save();
	}

	/**
	 * Returns an array of order item data.
	 *
	 * @param $order
	 *
	 * @return array
	 */
	private function get_items( $order ) {
		$order_items = $order->get_items();
		$items       = [];

		foreach ( $order_items as $id => $item ) {
			$items[] = $item->get_data();
		}

		foreach ( $items as &$item ) {
			$item = $this->clean_data( $item );
		}

		return $items;
	}
}
