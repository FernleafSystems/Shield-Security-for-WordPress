<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use PHPUnit\Framework\TestCase;

class LocalSiteDefinitionsTest extends TestCase {

	protected function tearDown() :void {
		\putenv( 'SHIELD_BROWSER_LANE_INDEX' );
		parent::tearDown();
	}

	public function testTestFromEnvironmentDefaultsToLegacyManualTestSite() :void {
		\putenv( 'SHIELD_BROWSER_LANE_INDEX' );

		$this->assertSame( 'test', LocalSiteDefinitions::testFromEnvironment()->key() );
	}

	public function testTestFromEnvironmentUsesInheritedBrowserLane() :void {
		\putenv( 'SHIELD_BROWSER_LANE_INDEX=2' );

		$definition = LocalSiteDefinitions::testFromEnvironment();

		$this->assertSame( 'browser-lane-2', $definition->key() );
		$this->assertSame( 'http://127.0.0.1:8891', $definition->siteUrl() );
	}

	public function testTestFromEnvironmentFailsFastWhenLaneIndexIsInvalid() :void {
		\putenv( 'SHIELD_BROWSER_LANE_INDEX=second' );

		$this->expectExceptionMessage( 'SHIELD_BROWSER_LANE_INDEX must be a positive integer.' );

		LocalSiteDefinitions::testFromEnvironment();
	}
}
