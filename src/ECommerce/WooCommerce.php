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
use WC_Product;

class WooCommerce {
	const PURCHASE_TRACKED_META_KEY = '_plausible_analytics_purchase_tracked';

	const CUSTOM_PROPERTIES         = [
		'cart_total',
		'cart_total_items',
		'customer_id',
		'id',
		'name',
		'order_id',
		'price',
		'product_id',
		'quantity',
		'shipping',
		'subtotal',
		'subtotal_tax',
		'tax_class',
		'total',
		'total_tax',
		'variation_id',
	];

	/**
	 * @var string $track_add_to_cart_event_label
	 */
	private $track_add_to_cart_event_label;

	/**
	 * @var string $track_remove_cart_item_event_label
	 */
	private $track_remove_cart_item_event_label;

	/**
	 * @var string $track_entered_checkout_event_label
	 */
	private $track_entered_checkout_event_label;

	/**
	 * @var string $track_purchase_event_label
	 */
	private $track_purchase_event_label;

	/**
	 * Build class.
	 */
	public function __construct() {
		$this->track_add_to_cart_event_label      = __( 'Add Item To Cart', 'plausible-analytics' );
		$this->track_remove_cart_item_event_label = __( 'Remove Cart Item', 'plausible-analytics' );
		$this->track_entered_checkout_event_label = __( 'Entered Checkout', 'plausible-analytics' );
		$this->track_purchase_event_label         = __( 'Purchase', 'plausible-analytics' );

		$this->init();
	}

	/**
	 * Filter and action hooks.
	 *
	 * @return void
	 */
	private function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'add_js' ], 1 );
		add_filter( 'woocommerce_store_api_add_to_cart_data', [ $this, 'add_http_referer' ], 10, 2 );
		add_filter( 'woocommerce_before_add_to_cart_button', [ $this, 'insert_track_form_submit_class_name' ] );
		/**
		 * @todo We should use woocommerce_add_to_cart action instead, but that currently doesn't trigger on consecutive adds to the cart. Fix when resolved in WC.
		 * @see  https://wordpress.org/support/topic/woocommerce_add_to_cart-action-isnt-triggered-on-consecutive-items/
		 */
		add_filter( 'woocommerce_store_api_validate_add_to_cart', [ $this, 'track_add_to_cart' ], 10, 2 );
		add_action( 'woocommerce_remove_cart_item', [ $this, 'track_remove_cart_item' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'track_entered_checkout' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'track_purchase' ] );
	}

	/**
	 * Enqueue required JS in frontend.
	 *
	 * @return void
	 */
	public function add_js() {
		// Causes errors in checkout and isn't needed either way.
		if ( is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'plausible-woocommerce-compatibility',
			PLAUSIBLE_ANALYTICS_PLUGIN_URL . 'assets/dist/js/plausible-compatibility-woocommerce.js',
			[],
			filemtime( PLAUSIBLE_ANALYTICS_PLUGIN_DIR . 'assets/dist/js/plausible-compatibility-woocommerce.js' )
		);
	}

	/**
	 * A bit of a hacky approach to ensuring the _wp_http_referer header is available to us when hitting the Proxy in @see self::track_add_to_cart()
	 * and @see self::track_remove_cart_item().
	 *
	 * @param $add_to_cart_data
	 * @param $request
	 *
	 * @return mixed
	 */
	public function add_http_referer( $add_to_cart_data, $request ) {
		$http_referer = $request->get_param( '_wp_http_referer' );

		if ( ! empty( $http_referer ) ) {
			$_REQUEST[ '_wp_http_referer' ] = sanitize_url( $http_referer );
		}

		return $add_to_cart_data;
	}

	/**
	 * A hacky approach (with lack of a proper solution) to make sure Add To Cart events are tracked on simple product pages.
	 *
	 * @return void
	 */
	public function insert_track_form_submit_class_name() {
		?>
		<script>
			let cart = document.getElementsByClassName('cart');

			if (cart.length > 0) {
				for (let form of cart) {
					form.classList.add('plausible-event-name=<?php echo str_replace( ' ', '+', $this->track_add_to_cart_event_label ); ?>')
				}
			}
		</script>
		<?php
	}

	/**
	 * @param WC_Product $product          General information about the product added to cart.
	 * @param array      $add_to_cart_data Cart data for the product added to the cart, e.g. quantity, variation ID, etc.
	 *
	 * @return void
	 */
	public function track_add_to_cart( $product, $add_to_cart_data ) {
		$product_data  = $this->clean_data( $product->get_data() );
		$added_to_cart = $this->clean_data( $add_to_cart_data );
		$cart          = WC()->cart;
		$props         = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_add_to_cart_custom_properties',
			[
				'props' => [
					'product_name'     => $product_data[ 'name' ],
					'product_id'       => $added_to_cart[ 'id' ],
					'quantity'         => $added_to_cart[ 'quantity' ],
					'price'            => $product_data[ 'price' ],
					'tax_class'        => $product_data[ 'tax_class' ],
					'cart_total_items' => count( $cart->get_cart_contents() ),
					'cart_total'       => $cart->get_total(),
				],
			]
		);
		$proxy         = new Proxy( false );

		$proxy->do_request( $this->track_add_to_cart_event_label, null, null, $props );
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
		$props                  = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_remove_cart_item_custom_properties',
			[
				'props' => [
					'product_id'       => $item_removed_from_cart[ 'product_id' ],
					'variation_id'     => $item_removed_from_cart[ 'variation_id' ],
					'quantity'         => $item_removed_from_cart[ 'quantity' ],
					'removed_item'     => $item_removed_from_cart,
					'cart_total_items' => count( $cart_contents ),
					'cart_total'       => $cart->get_total(),
				],
			]
		);
		$proxy                  = new Proxy( false );

		$proxy->do_request( $this->track_remove_cart_item_event_label, null, null, $props );
	}

	/**
	 * @return void
	 */
	public function track_entered_checkout() {
		if ( ! is_checkout() ) {
			return;
		}

		$session = WC()->session;
		$cart    = WC()->cart;
		$props   = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_entered_checkout_custom_properties',
			[
				'customer_id' => $session->get_customer_id(),
				'subtotal'    => $cart->get_subtotal(),
				'shipping'    => $cart->get_shipping_total(),
				'tax'         => $cart->get_total_tax(),
				'total'       => $cart->get_total(),
			]
		);
		$props   = wp_json_encode( $props );

		echo sprintf( ECommerce::SCRIPT_WRAPPER, "window.plausible( '$this->track_entered_checkout_event_label', $props )" );
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

		$props = apply_filters(
			'plausible_analytics_ecommerce_woocommerce_track_purchase_custom_properties',
			[
				'transaction_id' => $order->get_transaction_id(),
				'order_id'       => $order_id,
				'customer_id'    => $order->get_customer_id(),
			]
		);
		$props = wp_json_encode(
			[
				'revenue' => [ 'amount' => number_format_i18n( $order->get_total(), 2 ), 'currency' => $order->get_currency() ],
				'props'   => $props,
			]
		);

		echo sprintf( ECommerce::SCRIPT_WRAPPER, "window.plausible( '$this->track_purchase_event_label', $props )" );

		$order->add_meta_data( self::PURCHASE_TRACKED_META_KEY, true );
		$order->save();
	}
}
