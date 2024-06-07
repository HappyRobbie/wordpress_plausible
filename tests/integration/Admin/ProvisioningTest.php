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
use function Brain\Monkey\Functions\when;

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
		$settings[ 'enhanced_measurements' ] = [
			'404',
			'outbound-links',
			'file-downloads',
			'search',
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
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'Search', 'id' => 444, 'path' => null ] ),
					'goal_type' => 'Goal.Pageview',
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

		$this->assertCount( 4, $goal_ids );
		$this->assertArrayHasKey( 111, $goal_ids );
		$this->assertArrayHasKey( 222, $goal_ids );
		$this->assertArrayHasKey( 333, $goal_ids );
		$this->assertArrayHasKey( 444, $goal_ids );

		delete_option( 'plausible_analytics_enhanced_measurements_goal_ids' );
	}

	/**
	 * @see Provisioning::maybe_create_woocommerce_funnels()
	 * @return void
	 * @throws ApiException
	 */
	public function testCreateWooCommerceGoals() {
		$settings    = [
			'enhanced_measurements' => [
				'revenue',
			],
		];
		$mock        = $this->getMockBuilder( Client::class )->onlyMethods( [ 'create_goals' ] )->getMock();
		$goals_array = [
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'Add Item To Cart', 'id' => 112, 'path' => null ] ),
					'goal_type' => 'Goal.CustomEvent',
				]
			),
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'Remove Cart Item', 'id' => 223, 'path' => null ] ),
					'goal_type' => 'Goal.CustomEvent',
				]
			),
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'Entered Checkout', 'id' => 334, 'path' => null ] ),
					'goal_type' => 'Goal.CustomEvent',
				]
			),
			new Goal(
				[
					'goal'      => new GoalPageviewAllOfGoal( [ 'display_name' => 'Purchase', 'id' => 445, 'path' => null ] ),
					'goal_type' => 'Goal.Revenue',
				]
			),
		];
		$goals       = new Client\Model\GoalListResponse();

		$goals->setGoals( $goals_array );
		$goals->setMeta( new Client\Model\GoalListResponseMeta() );
		$mock->method( 'create_goals' )->willReturn( $goals );

		$class = new Provisioning( $mock );

		add_filter( 'plausible_analytics_integrations_woocommerce', '__return_true' );
		when( 'get_woocommerce_currency' )->justReturn( 'EUR' );

		$class->maybe_create_woocommerce_funnels( [], $settings );

		remove_filter( 'plausible_analytics_integrations_woocommerce', '__return_true' );

		$goal_ids = get_option( 'plausible_analytics_enhanced_measurements_goal_ids' );

		$this->assertCount( 4, $goal_ids );
		$this->assertArrayHasKey( 112, $goal_ids );
		$this->assertArrayHasKey( 223, $goal_ids );
		$this->assertArrayHasKey( 334, $goal_ids );
		$this->assertArrayHasKey( 445, $goal_ids );

		delete_option( 'plausible_analytics_enhanced_measurements_goal_ids' );
	}
}
