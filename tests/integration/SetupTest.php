<?php
/**
 * @package Plausible Analytics Integration Tests - Setup
 */

namespace Plausible\Analytics\Tests\Integration;

use Exception;
use Plausible\Analytics\Tests\TestCase;
use Plausible\Analytics\WP\Helpers;

class Setup extends TestCase {
	/**
	 * @see \Plausible\Analytics\WP\Setup::create_cache_dir()
	 * @throws Exception
	 */
	public function testCreateCacheDir() {
		$class = new \Plausible\Analytics\WP\Setup();

		$class->create_cache_dir();

		$this->assertDirectoryExists( Helpers::get_proxy_resource( 'cache_dir' ) );
	}
}
