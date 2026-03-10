<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceStaticAnalysisLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingSourceAnalyzeSetupCacheCoordinator;
use PHPUnit\Framework\TestCase;

class SourceStaticAnalysisLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-analyze-lane-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testCacheHitSkipsBuildConfigAndRunsPhpStan() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$setupCoordinator = $this->createSetupCoordinator( false, 'fingerprint-a' );
		$lane = new SourceStaticAnalysisLane( $processRunner, $setupCoordinator );

		$exitCode = $this->runLaneSilenced( $lane, false );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertStringContainsString( 'phpstan', \implode( ' ', $processRunner->calls[ 0 ][ 'command' ] ) );
		$this->assertCount( 0, $setupCoordinator->persistCalls );
	}

	public function testCacheMissRunsBuildConfigThenPhpStan() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$setupCoordinator = $this->createSetupCoordinator( true, 'fingerprint-b' );
		$lane = new SourceStaticAnalysisLane( $processRunner, $setupCoordinator );

		$exitCode = $this->runLaneSilenced( $lane, false );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertStringContainsString( 'build-config.php', \implode( ' ', $processRunner->calls[ 0 ][ 'command' ] ) );
		$this->assertStringContainsString( 'phpstan', \implode( ' ', $processRunner->calls[ 1 ][ 'command' ] ) );
		$this->assertCount( 1, $setupCoordinator->persistCalls );
		$this->assertSame( 'fingerprint-b', $setupCoordinator->persistCalls[ 0 ][ 'fingerprint' ] );
	}

	public function testRefreshSetupClearsStateBeforeEvaluation() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$setupCoordinator = $this->createSetupCoordinator( true, 'fingerprint-c' );
		$lane = new SourceStaticAnalysisLane( $processRunner, $setupCoordinator );

		$exitCode = $this->runLaneSilenced( $lane, true );
		$this->assertSame( 0, $exitCode );
		$this->assertSame( 1, $setupCoordinator->clearCalls );
	}

	private function runLaneSilenced( SourceStaticAnalysisLane $lane, bool $refreshSetup ) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $refreshSetup );
		}
		finally {
			\ob_end_clean();
		}
	}

	private function createSetupCoordinator(
		bool $needsBuildConfig,
		string $fingerprint
	) :RecordingSourceAnalyzeSetupCacheCoordinator {
		return new RecordingSourceAnalyzeSetupCacheCoordinator( $needsBuildConfig, $fingerprint );
	}
}
