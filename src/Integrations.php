<?php

/**
 * Plausible Analytics | Integrations
 *
 * @since      2.1.0
 * @package    WordPress
 * @subpackage Plausible Analytics
 */

namespace Plausible\Analytics\WP;

/**
 * @codeCoverageIgnore Because the code is very straight-forward.
 */
class Integrations {
	const SCRIPT_WRAPPER = '<script defer id="plausible-analytics-integration-tracking">document.addEventListener("DOMContentLoaded", () => { %s });</script>';

	/**
	 * Build class.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Run available integrations.
	 *
	 * @return void
	 */
	private function init() {
		// WooCommerce
		if ( self::is_wc_active() ) {
			new Integrations\WooCommerce();
		}

		// Easy Digital Downloads
		if ( self::is_edd_active() ) {
			// new Integrations\EDD();
		}
	}

	/**
	 * Checks if WooCommerce is installed and activated.
	 *
	 * @return bool
	 */
	public static function is_wc_active() {
		return apply_filters( 'plausible_analytics_integrations_woocommerce', function_exists( 'WC' ) );
	}

	/**
	 * Checks if Easy Digital Downloads is installed and activated.
	 *
	 * @return bool
	 */
	public static function is_edd_active() {
		return apply_filters( 'plausible_analytics_integrations_edd', function_exists( 'EDD' ) );
	}
}
