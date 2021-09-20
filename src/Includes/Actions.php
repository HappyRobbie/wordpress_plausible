<?php
/**
 * Plausible Analytics | Actions.
 *
 * @since 1.0.0
 *
 * @package    WordPress
 * @subpackage Plausible Analytics
 */

namespace Plausible\Analytics\WP\Includes;

use Plausible\Analytics\WP\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {

	/**
	 * Constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_node' ], 100 );
	}

	/**
	 * Register Assets.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_assets() {
		$settings  = Helpers::get_settings();
		$user_role = Helpers::get_user_role();

		// Bailout, if `administrator` user role accessing frontend.
		if (
			! empty( $user_role ) &&
			! in_array( $user_role, $settings['track_analytics'], true )
		) {
			return;
		}

		wp_enqueue_script( 'plausible-analytics', Helpers::get_analytics_url(), '', PLAUSIBLE_ANALYTICS_VERSION );

		// Goal tracking inline script (Don't disable this as it is required by 404).
		wp_add_inline_script( 'plausible-analytics', 'window.plausible = window.plausible || function() { (window.plausible.q = window.plausible.q || []).push(arguments) }' );

		// Track 404 pages.
		if ( apply_filters( 'plausible_analytics_enable_404', true ) && is_404() ) {
			wp_add_inline_script( 'plausible-analytics', 'plausible("404",{ props: { path: document.location.pathname } });' );
		}
	}

	/**
	 * Admin bar node for pages.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @return void
	 */

	public function admin_bar_node( $admin_bar ) {
		// Add main admin bar node
		$args = [
			'id' => 'plausible-admin-bar',
			'title' => 'Plausible Analytics',
		];
		$admin_bar->add_node( $args );

		// Add sub menu items
		$args = [];
		$args[] = [
			'id' => 'view-analytics',
			'title' => 'View Analytics',
			'href' => admin_url( 'index.php?page=plausible-analytics-statistics' ),
			'parent' => 'plausible-admin-bar'
		];

		// Add link to individual page stats
		if ( is_page() || is_single() ) {
			$args[] = [
				'id' => 'view-page-analytics',
				'title' => 'View Page Analytics',
				'href' => '#',
				'parent' => 'plausible-admin-bar'
			];
		}
		
		$args[] = [
			'id' => 'settings',
			'title' => 'Settings',
			'href' => admin_url( 'options-general.php?page=plausible-analytics' ),
			'parent' => 'plausible-admin-bar'
		];
		foreach ( $args as $arg ) {
			$admin_bar->add_node( $arg );
		}
	}
}
