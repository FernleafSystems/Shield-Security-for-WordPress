<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSitePairManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CrossSiteTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		foreach ( [
			'CI',
			'SHIELD_CROSS_SITE_MODE',
		] as $name ) {
			\putenv( $name );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunUsesWarmModeByDefaultForLocalRuns() :void {
		\putenv( 'CI' );
		\putenv( 'SHIELD_CROSS_SITE_MODE' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-' );
		$manager = $this->buildPairManagerMock( 'warm' );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $projectRoot )
		);

		$this->assertSame( 0, $exitCode );
		$this->assertTrue( \is_file( $projectRoot.'/tmp/cross-site-test-lane/lane.lock' ) );
	}

	public function testRunUsesCleanModeByDefaultForCiRuns() :void {
		\putenv( 'CI=true' );
		\putenv( 'SHIELD_CROSS_SITE_MODE' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-ci-' );
		$manager = $this->buildPairManagerMock( 'clean' );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $projectRoot )
		);

		$this->assertSame( 0, $exitCode );
	}

	public function testExplicitModeBeatsEnvironment() :void {
		\putenv( 'CI=true' );
		\putenv( 'SHIELD_CROSS_SITE_MODE=clean' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-explicit-' );
		$manager = $this->buildPairManagerMock( 'warm', true );

		$exitCode = $this->runQuietly(
			static fn() :int => ( new CrossSiteTestLane( $manager ) )->run(
				$projectRoot,
				[
					'mode' => 'warm',
					'show_setup_output' => true,
				]
			)
		);

		$this->assertSame( 0, $exitCode );
	}

	public function testSuccessfulRunWritesOnlyFinalResultLine() :void {
		\putenv( 'CI' );
		\putenv( 'SHIELD_CROSS_SITE_MODE' );
		$projectRoot = $this->createTrackedTempDir( 'shield-cross-site-lane-output-' );
		$manager = $this->buildPairManagerMock( 'warm' );

		\ob_start();
		try {
			$exitCode = ( new CrossSiteTestLane( $manager ) )->run( $projectRoot );
			$output = (string)\ob_get_contents();
		}
		finally {
			\ob_end_clean();
		}

		$this->assertSame( 0, $exitCode );
		$this->assertSame( 'Cross-site test lane passed'.\PHP_EOL, $output );
		$this->assertStringNotContainsString( 'Mode:', $output );
		$this->assertStringNotContainsString( 'Stage:', $output );
	}

	/**
	 * @return CrossSitePairManager&MockObject
	 */
	private function buildPairManagerMock( string $expectedMode, bool $showSetupOutput = false ) :CrossSitePairManager {
		$manager = $this->getMockBuilder( CrossSitePairManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [
				'prepare',
				'runImportExportScenario',
			] )
			->getMock();
		$manager->expects( $this->once() )
			->method( 'prepare' )
			->with(
				$this->isType( 'string' ),
				$expectedMode,
				$showSetupOutput
			);
		$manager->expects( $this->once() )
			->method( 'runImportExportScenario' )
			->with( $this->isType( 'string' ) );

		return $manager;
	}

	/**
	 * @param callable():int $callback
	 */
	private function runQuietly( callable $callback ) :int {
		\ob_start();
		try {
			return $callback();
		}
		finally {
			\ob_end_clean();
		}
	}
}
