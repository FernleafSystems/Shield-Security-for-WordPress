<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalTestSiteSlots;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalSiteDefinitionsTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		\putenv( 'SHIELD_BROWSER_LANE_INDEX' );
		$this->cleanupTrackedTempDirs();
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
		$this->assertSame( 'http://127.0.0.1:8902', $definition->siteUrl() );
	}

	public function testTestFromEnvironmentFailsFastWhenLaneIndexIsInvalid() :void {
		\putenv( 'SHIELD_BROWSER_LANE_INDEX=second' );

		$this->expectExceptionMessage( 'SHIELD_BROWSER_LANE_INDEX must be a positive integer.' );

		LocalSiteDefinitions::testFromEnvironment();
	}

	public function testMainCheckoutKeepsTheDefaultTestSite() :void {
		$checkout = $this->createTrackedTempDir( 'shield-main-checkout-' );
		\mkdir( Path::join( $checkout, '.git' ) );

		$definition = LocalSiteDefinitions::testForCheckout( $checkout, new LocalTestSiteSlots( $this->createTrackedTempDir( 'shield-slots-' ) ) );

		$this->assertSame( 'shield-test-site', $definition->composeProjectName() );
		$this->assertSame( 8889, $definition->sitePort() );
	}

	public function testLinkedWorktreesGetStableSeparateTestSites() :void {
		$slots = new LocalTestSiteSlots( $this->createTrackedTempDir( 'shield-slots-' ) );
		$first = $this->createLinkedWorktree();
		$second = $this->createLinkedWorktree();

		$firstDefinition = LocalSiteDefinitions::testForCheckout( $first, $slots );
		$secondDefinition = LocalSiteDefinitions::testForCheckout( $second, $slots );

		$this->assertSame( 'test', $firstDefinition->key() );
		$this->assertSame( 'shield-test-site-wt1', $firstDefinition->composeProjectName() );
		$this->assertSame( 'http://127.0.0.1:8871', $firstDefinition->siteUrl() );
		$this->assertSame( 'shield_test_site_wt1', $firstDefinition->dbName() );
		$this->assertSame( 'shield-test-site-wt2', $secondDefinition->composeProjectName() );
		$this->assertSame( 8872, $secondDefinition->sitePort() );
		$this->assertSame( 'shield-test-site-wt1', LocalSiteDefinitions::testForCheckout( $first, $slots )->composeProjectName() );
	}

	public function testRemovedWorktreeSlotsAreReused() :void {
		$slots = new LocalTestSiteSlots( $this->createTrackedTempDir( 'shield-slots-' ) );
		$removed = $this->createLinkedWorktree();
		$this->assertSame( 1, $slots->slotFor( $removed ) );
		\unlink( Path::join( $removed, '.git' ) );
		\rmdir( $removed );

		$this->assertSame( 1, $slots->slotFor( $this->createLinkedWorktree() ) );
	}

	private function createLinkedWorktree() :string {
		$checkout = $this->createTrackedTempDir( 'shield-linked-worktree-' );
		\file_put_contents( Path::join( $checkout, '.git' ), 'gitdir: /repo/.git/worktrees/example' );
		return $checkout;
	}
}
