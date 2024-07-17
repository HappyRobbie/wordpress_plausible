<?php
/**
 * @package Plausible Analytics Unit Tests - ClientFactory
 */

namespace Plausible\Analytics\Tests\Unit;

use Plausible\Analytics\Tests\TestCase;
use Plausible\Analytics\WP\Client;
use Plausible\Analytics\WP\ClientFactory;

class ClientFactoryTest extends TestCase {
	/**
	 * @see ClientFactory::build()
	 */
	public function testBuild() {
		$clientFactory = new ClientFactory();

		$this->assertInstanceOf( Client::class, $clientFactory );
	}
}
