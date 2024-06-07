<?php

namespace Plausible\Analytics\WP;

/**
 * Loads and registers plugin functionality through WordPress hooks.
 *
 * @since 1.0.0
 */
final class Plugin {
	/**
	 * Registers functionality with WordPress hooks.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function register() {
		// Register services used throughout the plugin. (WP Rocket runs at priority 10)
		add_action( 'plugins_loaded', [ $this, 'register_services' ], 9 );

		// Load text domain.
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Registers the individual services of the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function register_services() {
		if ( is_admin() ) {
			new Admin\Upgrades();
			new Admin\Settings\Page();
			new Admin\Filters();
			new Admin\Actions();
			new Admin\Module();
			new Admin\Provisioning();
		}

		if ( Helpers::is_enhanced_measurement_enabled( 'revenue' ) ) {
			new Integrations(); // @codeCoverageIgnore
		}

		new Actions();
		new Ajax();
		new Compatibility();
		new Filters();
		new Proxy();
		new Setup();
	}

	/**
	 * Loads the plugin's translated strings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 *
	 * @codeCoverageIgnore
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'plausible-analytics',
			false,
			dirname( plugin_basename( PLAUSIBLE_ANALYTICS_PLUGIN_FILE ) ) . '/languages/'
		);
	}
}
