<?php
/**
 * Plausible Analytics | Provisioning.
 *
 * @since      2.0.0
 * @package    WordPress
 * @subpackage Plausible Analytics
 */

namespace Plausible\Analytics\WP\Admin;

use Plausible\Analytics\WP\Client;
use Plausible\Analytics\WP\Client\ApiException;
use Plausible\Analytics\WP\Client\Model\GoalCreateRequestCustomEvent;
use Plausible\Analytics\WP\Helpers;
use Plausible\Analytics\WP\Integrations;
use Plausible\Analytics\WP\Integrations\WooCommerce;

class Provisioning {
	/**
	 * @var Client $client
	 */
	private $client;

	/**
	 * @var string[] $custom_event_goals
	 */
	private $custom_event_goals = [];

	/**
	 * @var string[] $custom_pageview_properties
	 */
	private $custom_pageview_properties = [
		'author',
		'category',
	];

	/**
	 * Build class.
	 *
	 * @param bool|Client $client Allows for mocking during CI.
	 *
	 * @throws ApiException
	 * @codeCoverageIgnore
	 */
	public function __construct( $client = null ) {
		/**
		 * cURL or allow_url_fopen ini setting is required for GuzzleHttp to function properly.
		 */
		if ( ! extension_loaded( 'curl' ) && ! ini_get( 'allow_url_fopen' ) ) {
			add_action( 'init', [ $this, 'add_curl_error' ] );

			return;
		}

		$this->client = $client;

		if ( ! $this->client ) {
			$this->client = new Client();
		}

		$this->custom_event_goals = [
			'404'            => __( '404', 'plausible-analytics' ),
			'outbound-links' => __( 'Outbound Link: Click', 'plausible-analytics' ),
			'file-downloads' => __( 'File Download', 'plausible-analytics' ),
		];

		$this->init();
	}

	/**
	 * Action & filter hooks.
	 *
	 * @return void
	 * @throws ApiException
	 *
	 * @codeCoverageIgnore
	 */
	private function init() {
		if ( ! $this->client->validate_api_token() ) {
			return; // @codeCoverageIgnore
		}

		add_action( 'update_option_plausible_analytics_settings', [ $this, 'create_shared_link' ], 10, 2 );
		add_action( 'update_option_plausible_analytics_settings', [ $this, 'maybe_create_goals' ], 10, 2 );
		add_action( 'update_option_plausible_analytics_settings', [ $this, 'maybe_create_woocommerce_goals' ], 10, 2 );
		add_action( 'update_option_plausible_analytics_settings', [ $this, 'maybe_delete_goals' ], 11, 2 );
		add_action( 'update_option_plausible_analytics_settings', [ $this, 'maybe_create_custom_properties' ], 11, 2 );
	}

	/**
	 * Show an error on the settings screen if cURL isn't enabled on this machine.
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore
	 */
	public function add_curl_error() {
		Messages::set_error(
			__(
				'cURL is not enabled on this server, which means API provisioning will not work. Please contact your hosting provider to enable the cURL module or <code>allow_url_fopen</code>.',
				'plausible-analytics'
			)
		);
	}

	/**
	 * Create shared link when Enable Analytics Dashboard option is enabled.
	 *
	 * @param $old_settings
	 * @param $settings
	 */
	public function create_shared_link( $old_settings, $settings ) {
		if ( empty( $settings[ 'enable_analytics_dashboard' ] ) ) {
			return; // @codeCoverageIgnore
		}

		$this->client->create_shared_link();
	}

	/**
	 * Create Custom Event Goals for enabled Enhanced Measurements.
	 *
	 * @param $old_settings
	 * @param $settings
	 */
	public function maybe_create_goals( $old_settings, $settings ) {
		$enhanced_measurements = array_filter( $settings[ 'enhanced_measurements' ] );

		if ( empty( $enhanced_measurements ) ) {
			return; // @codeCoverageIgnore
		}

		$custom_event_keys = array_keys( $this->custom_event_goals );
		$goals             = [];

		foreach ( $enhanced_measurements as $measurement ) {
			if ( ! in_array( $measurement, $custom_event_keys ) ) {
				continue; // @codeCoverageIgnore
			}

			$goals[] = $this->create_request_custom_event( $this->custom_event_goals[ $measurement ] );
		}

		$this->create_goals( $goals );
	}

	/**
	 * @param string $name     Event Name
	 * @param string $type     CustomEvent|Revenue|Pageview
	 * @param string $currency Required if $type is Revenue
	 *
	 * @return GoalCreateRequestCustomEvent
	 */
	private function create_request_custom_event( $name, $type = 'CustomEvent', $currency = '' ) {
		$props = [
			'goal'      => [
				'event_name' => $name,
			],
			'goal_type' => "Goal.$type",
		];

		if ( $type === 'Revenue' ) {
			$props[ 'goal' ][ 'currency' ] = $currency;
		}

		return new Client\Model\GoalCreateRequestCustomEvent( $props );
	}

	/**
	 * Create the goals using the API client and updates the IDs in the database.
	 *
	 * @param $goals
	 *
	 * @return void
	 */
	private function create_goals( $goals ) {
		if ( empty( $goals ) ) {
			return; // @codeCoverageIgnore
		}

		$create_request = new Client\Model\GoalCreateRequestBulkGetOrCreate();
		$create_request->setGoals( $goals );
		$response = $this->client->create_goals( $create_request );

		if ( $response->valid() ) {
			$goals = $response->getGoals();
			$ids   = get_option( 'plausible_analytics_enhanced_measurements_goal_ids', [] );

			foreach ( $goals as $goal ) {
				$goal                  = $goal->getGoal();
				$ids[ $goal->getId() ] = $goal->getDisplayName();
			}

			if ( ! empty( $ids ) ) {
				update_option( 'plausible_analytics_enhanced_measurements_goal_ids', $ids );
			}
		}
	}

	/**
	 * @param $old_settings
	 * @param $settings
	 *
	 * @return void
	 */
	public function maybe_create_woocommerce_goals( $old_settings, $settings ) {
		if ( ! Helpers::is_enhanced_measurement_enabled( 'revenue', $settings[ 'enhanced_measurements' ] ) || ! Integrations::is_wc_active() ) {
			return; // @codeCoverageIgnore
		}

		$goals       = [];
		$woocommerce = new WooCommerce( false );

		foreach ( $woocommerce->event_goals as $event_key => $event_goal ) {
			if ( $event_key === 'purchase' ) {
				$goals[] = $this->create_request_custom_event( $event_goal, 'Revenue', get_woocommerce_currency() );

				continue;
			}

			$goals[] = $this->create_request_custom_event( $event_goal );
		}

		$this->create_goals( $goals );
	}

	/**
	 * Delete Custom Event Goals when an Enhanced Measurement is disabled.
	 *
	 * @param $old_settings
	 * @param $settings
	 *
	 * @codeCoverageIgnore Because we don't want to test if the API is working.
	 */
	public function maybe_delete_goals( $old_settings, $settings ) {
		$enhanced_measurements_old = array_filter( $old_settings[ 'enhanced_measurements' ] );
		$enhanced_measurements     = array_filter( $settings[ 'enhanced_measurements' ] );
		$disabled_settings         = array_diff( $enhanced_measurements_old, $enhanced_measurements );

		if ( empty( $disabled_settings ) ) {
			return;
		}

		$goals = get_option( 'plausible_analytics_enhanced_measurements_goal_ids', [] );

		foreach ( $goals as $id => $name ) {
			$key = array_search( $name, $this->custom_event_goals );

			if ( ! in_array( $key, $disabled_settings ) ) {
				continue; // @codeCoverageIgnore
			}

			$this->client->delete_goal( $id );
		}
	}

	/**
	 * @param array $old_settings
	 * @param array $settings
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore Because we don't want to test if the API is working.
	 */
	public function maybe_create_custom_properties( $old_settings, $settings ) {
		$enhanced_measurements = $settings[ 'enhanced_measurements' ];

		if ( ! Helpers::is_enhanced_measurement_enabled( 'pageview-props', $enhanced_measurements ) &&
			! Helpers::is_enhanced_measurement_enabled( 'revenue', $enhanced_measurements ) ) {
			return; // @codeCoverageIgnore
		}

		$create_request = new Client\Model\CustomPropEnableRequestBulkEnable();
		$properties     = [];

		/**
		 * Enable Custom Properties for Authors & Categories option.
		 */
		if ( Helpers::is_enhanced_measurement_enabled( 'pageview-props', $enhanced_measurements ) ) {
			foreach ( $this->custom_pageview_properties as $property ) {
				$properties[] = new Client\Model\CustomProp( [ 'custom_prop' => [ 'key' => $property ] ] );
			}
		}

		/**
		 * Create Custom Properties for WooCommerce integration.
		 */
		if ( Helpers::is_enhanced_measurement_enabled( 'revenue', $enhanced_measurements ) && Integrations::is_wc_active() ) {
			foreach ( WooCommerce::CUSTOM_PROPERTIES as $property ) {
				$properties[] = new Client\Model\CustomProp( [ 'custom_prop' => [ 'key' => $property ] ] );
			}
		}

		$create_request->setCustomProps( $properties );

		$this->client->enable_custom_property( $create_request );
	}
}
