<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSitePairManager;
use FernleafSystems\ShieldPlatform\Tooling\Testing\CrossSiteTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;

class CrossSiteTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunsOneFixedPublicThenCurrentLifecycleAndFinalizes() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-lane-order-' );
		$manager = new CrossSiteLifecycleRecordingPairManager();

		$exitCode = $this->runQuietly( static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $root ) );

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [
			'prepare',
			'prepare-public',
			'run-public',
			'prepare-current',
			'run-current',
			'cleanup',
		], $manager->calls );
	}

	public function testForwardsDiagnosticSetupOutputWithoutChangingLifecycle() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-lane-output-' );
		$manager = new CrossSiteLifecycleRecordingPairManager();

		$exitCode = $this->runQuietly( static fn() :int => ( new CrossSiteTestLane( $manager ) )->run(
			$root,
			[ 'show_setup_output' => true ]
		) );

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [
			'prepare:output',
			'prepare-public:output',
			'run-public',
			'prepare-current:output',
			'run-current',
			'cleanup',
		], $manager->calls );
	}

	public function testFinalizesAfterAPrimaryScenarioFailure() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-lane-primary-failure-' );
		$manager = new CrossSiteLifecycleRecordingPairManager();
		$manager->publicFailure = new \RuntimeException( 'public scenario failed' );

		$exitCode = $this->runQuietly( static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $root ) );

		$this->assertSame( 1, $exitCode );
		$this->assertSame( [ 'prepare', 'prepare-public', 'run-public', 'cleanup' ], $manager->calls );
	}

	public function testSurfacesCleanupOnlyFailure() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-lane-cleanup-failure-' );
		$manager = new CrossSiteLifecycleRecordingPairManager();
		$manager->cleanupFailure = new \RuntimeException( 'cleanup failed' );

		$exitCode = $this->runQuietly( static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $root ) );

		$this->assertSame( 1, $exitCode );
		$this->assertSame( [
			'prepare',
			'prepare-public',
			'run-public',
			'prepare-current',
			'run-current',
			'cleanup',
		], $manager->calls );
	}

	public function testPreservesPrimaryAndCleanupFailures() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-lane-combined-failure-' );
		$manager = new CrossSiteLifecycleRecordingPairManager();
		$manager->publicFailure = new \RuntimeException( 'public scenario failed' );
		$manager->cleanupFailure = new \RuntimeException( 'cleanup failed' );

		$exitCode = $this->runQuietly( static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $root ) );

		$this->assertSame( 1, $exitCode );
		$this->assertSame( [ 'prepare', 'prepare-public', 'run-public', 'cleanup' ], $manager->calls );
	}

	public function testLockAcquisitionFailureLeavesPairManagerUntouched() :void {
		$root = $this->createTrackedTempDir( 'shield-cross-site-lane-lock-failure-' );
		$lockDir = $root.'/tmp/cross-site-test-lane';
		\mkdir( \dirname( $lockDir ), 0777, true );
		\file_put_contents( $lockDir, 'not a directory' );
		$manager = new CrossSiteLifecycleRecordingPairManager();

		$exitCode = $this->runQuietly( static fn() :int => ( new CrossSiteTestLane( $manager ) )->run( $root ) );

		$this->assertSame( 1, $exitCode );
		$this->assertSame( [], $manager->calls );
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

class CrossSiteLifecycleRecordingPairManager extends CrossSitePairManager {

	/** @var string[] */
	public array $calls = [];

	public ?\Throwable $publicFailure = null;

	public ?\Throwable $cleanupFailure = null;

	public function __construct() {
	}

	public function prepare( string $rootDir, bool $showSetupOutput = false ) :void {
		$this->calls[] = 'prepare'.( $showSetupOutput ? ':output' : '' );
	}

	public function preparePublicRuntimeScenario( string $rootDir, bool $showSetupOutput = false ) :void {
		$this->calls[] = 'prepare-public'.( $showSetupOutput ? ':output' : '' );
	}

	public function runPublicUpgradeScenario( string $rootDir ) :void {
		$this->calls[] = 'run-public';
		if ( $this->publicFailure instanceof \Throwable ) {
			throw $this->publicFailure;
		}
	}

	public function prepareCurrentRuntimeScenario( string $rootDir, bool $showSetupOutput = false ) :void {
		$this->calls[] = 'prepare-current'.( $showSetupOutput ? ':output' : '' );
	}

	public function runImportExportScenario( string $rootDir ) :void {
		$this->calls[] = 'run-current';
	}

	public function cleanupRun( string $rootDir ) :void {
		$this->calls[] = 'cleanup';
		if ( $this->cleanupFailure instanceof \Throwable ) {
			throw $this->cleanupFailure;
		}
	}
}
