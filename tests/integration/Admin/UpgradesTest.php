<?php
/**
 * @package Plausible Analytis integration tests - Upgrades
 */

namespace Plausible\Analytics\Tests\Integration\Admin;

use Plausible\Analytics\Tests\TestCase;
use Plausible\Analytics\WP\Admin\Upgrades;
use Plausible\Analytics\WP\Helpers;

class UpgradesTest extends TestCase {
	/**
	 * @see Upgrades::upgrade_to_210()
	 * @return void
	 */
	public function testUpgradeTo210() {
		$settings                            = Helpers::get_settings();
		$settings[ 'enhanced_measurements' ] = 'on';

		update_option( 'plausible_analytics_settings', $settings );

		$class = new Upgrades();
		$class->upgrade_to_210();

		$enhanced_measurements = Helpers::get_settings()[ 'enhanced_measurements' ];

		$this->assertIsArray( $enhanced_measurements );
	}
}
