<?php
/**
 * @package Plausible Analytis integration tests - Provisioning
 */

namespace Plausible\Analytics\Tests\Integration\Admin;

use Plausible\Analytics\Tests\TestCase;
use Plausible\Analytics\WP\Admin\Provisioning;
use Plausible\Analytics\WP\Client;
use Plausible\Analytics\WP\Client\ApiException;
use Plausible\Analytics\WP\Client\Model\Goal;
use Plausible\Analytics\WP\Client\Model\GoalPageviewAllOfGoal;
use Plausible\Analytics\WP\Helpers;

class ProvisioningTest extends TestCase {
	/**
	 * @see Provisioning::create_shared_link()
	 * @throws ApiException
	 */
	public function testCreateSharedLink() {
		$settings                                 = [];
		$settings[ 'enable_analytics_dashboard' ] = 1;
		$mock                                     = $this->getMockBuilder( Client::class )->onlyMethods( [ 'bulk_create_shared_links' ] )->getMock();
		$sharedLinkObject                         = new Client\Model\SharedLinkSharedLink(
			[
				'id'                 => 'test',
				'name'               => 'Test',
				'href'               => 'http://example.org/test',
				'password_protected' => false,
			]
		);
		$sharedLink                               = new Client\Model\SharedLink();

		$sharedLink->setSharedLink( $sharedLinkObject );
		$mock->method( 'bulk_create_shared_links' )->willReturn( $sharedLink );

		$class = new Provisioning( $mock );

		$class->create_shared_link( [], $settings );

		$sharedLink = Helpers::get_settings()[ 'shared_link' ];

		$this->assertEquals( 'http://example.org/test', $sharedLink );
	}

	/**
	 * @see Provisioning::maybe_create_goals()
	 * @throws ApiException
	 */
	public function testCreateGoals() {
		$settings                            = [];
		$settings[ 'enhanced_measurements' ] = [
			'404',
			'outbound-links',
			'file-downloads',
		];
		$mock                                = $this->getMockBuilder( Client::class )->onlyMethods( [ 'create_goals' ] )->getMock();
		$goals_array                         = [
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => '404', 'id' => 111, 'path' => null ] ),
					'goal_type' => 'Goal.CustomEvent',
				]
			),
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'Outbound Link: Click', 'id' => 222, 'path' => null ] ),
					'goal_type' => 'Goal.CustomEvent',
				]
			),
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'File Downloads', 'id' => 333, 'path' => null ] ),
					'goal_type' => 'Goal.CustomEvent',
				]
			),
		];
		$goals                               = new Client\Model\GoalListResponse();

		$goals->setGoals( $goals_array );
		$goals->setMeta( new Client\Model\GoalListResponseMeta() );
		$mock->method( 'create_goals' )->willReturn( $goals );

		$class = new Provisioning( $mock );

		$class->maybe_create_goals( [], $settings );

		$goal_ids = get_option( 'plausible_analytics_enhanced_measurements_goal_ids' );

		$this->assertCount( 3, $goal_ids );
		$this->assertArrayHasKey( 111, $goal_ids );
		$this->assertArrayHasKey( 222, $goal_ids );
		$this->assertArrayHasKey( 333, $goal_ids );
	}
}
