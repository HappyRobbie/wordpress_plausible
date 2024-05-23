<?php

/**
 * Plausible Analytics | ECommerce
 *
 * @since      2.1.0
 * @package    WordPress
 * @subpackage Plausible Analytics
 */

namespace Plausible\Analytics\WP;

class ECommerce {
	const SCRIPT_WRAPPER = '<script defer id="plausible-analytics-ecommerce-revenue-tracking">document.addEventListener("DOMContentLoaded", () => { %s });</script>';

	/**
	 * Build class.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Execute Ecommerce integrations.
	 *
	 * @return void
	 */
	private function init() {
		// WooCommerce
		if ( function_exists( 'WC' ) ) {
			new ECommerce\WooCommerce();
		}

		// Easy Digital Downloads
		if ( function_exists( 'EDD' ) ) {
			// new ECommerce\EDD();
		}
	}
}
