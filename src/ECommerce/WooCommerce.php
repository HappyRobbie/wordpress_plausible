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

class WooCommerce {
	const PURCHASE_TRACKED_META_KEY = '_plausible_analytics_purchase_tracked';

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
		add_action( 'woocommerce_thankyou', [ $this, 'track_purchase' ] );
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
			unset( $item[ 'taxes' ] );
			unset( $item[ 'meta_data' ] );
		}

		return $items;
	}
}
