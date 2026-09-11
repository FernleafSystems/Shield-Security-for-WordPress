<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingSourceRuntimeSetupCacheCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;

class SourceRuntimeTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-source-lane-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testCacheHitSkipsSetupCommands() :void {
		$processRunner = RecordingProcessRunner::strict( [] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => false,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$exitCode = $this->runLaneSilenced( $lane, false );
		$this->assertSame( 0, $exitCode );

		$this->assertTrue( $environmentResolver->assertDockerReadyCalled );
		$this->assertCount( 3, $dockerComposeExecutor->calls );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertSame(
			[ 'up', '-d', '--wait', '--wait-timeout', '60', 'mysql-latest' ],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertSame(
			[ 'build', 'test-runner-latest' ],
			$dockerComposeExecutor->calls[ 1 ][ 'sub_command' ]
		);
		$this->assertContains( 'test-runner-latest', $dockerComposeExecutor->calls[ 2 ][ 'sub_command' ] );
		$this->assertNotContains( 'test-runner-previous', $dockerComposeExecutor->calls[ 2 ][ 'sub_command' ] );
		$this->assertSourceDockerLabels( $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ] );
		$this->assertSourceDockerLabels( $dockerComposeExecutor->ignoredFailureCalls[ 0 ][ 'env_overrides' ] );
		$this->assertCount( 0, $processRunner->calls );
		$this->assertCount( 1, $setupCoordinator->persistCalls );

		foreach ( $dockerComposeExecutor->calls as $call ) {
			$this->assertFalse( $call[ 'show_docker_output' ] );
		}
		foreach ( $dockerComposeExecutor->ignoredFailureCalls as $call ) {
			$this->assertFalse( $call[ 'show_docker_output' ] );
		}
	}

	public function testRunCanIncludePreviousWordpress() :void {
		$processRunner = RecordingProcessRunner::strict( [] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => false,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$logDir = $this->createTrackedTempDir( 'shield-source-runtime-logs-' );
		$originalLogDir = \getenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
		\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$logDir );
		try {
			$exitCode = $this->runLaneSilenced( $lane, false, false, false, true );
		}
		finally {
			\putenv( \is_string( $originalLogDir ) ? 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$originalLogDir : 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
		}
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 4, $dockerComposeExecutor->calls );
		$this->assertSame(
			[ 'up', '-d', '--wait', '--wait-timeout', '60', 'mysql-latest', 'mysql-previous' ],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertSame(
			[ 'build', 'test-runner-latest', 'test-runner-previous' ],
			$dockerComposeExecutor->calls[ 1 ][ 'sub_command' ]
		);
		$runtimeCommands = $this->runtimeRunCommands( $dockerComposeExecutor );
		$this->assertCount( 2, $runtimeCommands );
		$this->assertContains( 'test-runner-latest', $runtimeCommands[ 0 ][ 'sub_command' ] );
		$this->assertContains( 'test-runner-previous', $runtimeCommands[ 1 ][ 'sub_command' ] );
		$this->assertFileExists( $logDir.'/runtime-latest.log' );
		$this->assertFileExists( $logDir.'/runtime-previous.log' );
	}

	public function testSetupMissRunsComposerBuildConfigAndNpmInstall() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 0 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => true,
			'needs_build_config' => true,
			'needs_npm_install' => true,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$exitCode = $this->runLaneSilenced( $lane, false );
		$this->assertSame( 0, $exitCode );

		$this->assertCount( 5, $dockerComposeExecutor->calls );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertSame( [ 'docker', 'volume', 'create' ], \array_slice( $processRunner->calls[ 0 ][ 'command' ], 0, 3 ) );
		$this->assertContains( '--label', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'com.fernleaf.harness=shield-plugin-source', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( 'com.fernleaf.lifecycle=reusable', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertStringContainsString(
			'npm ci --no-audit --no-fund && npm run build',
			\implode( ' ', $processRunner->calls[ 1 ][ 'command' ] )
		);
		$this->assertStringContainsString(
			'shield-source-node-modules-test:/app/node_modules',
			\implode( ' ', $processRunner->calls[ 1 ][ 'command' ] )
		);
		$this->assertContains(
			'com.fernleaf.harness=shield-plugin-source',
			$processRunner->calls[ 1 ][ 'command' ]
		);
		$this->assertContains(
			'com.fernleaf.lifecycle=transient',
			$processRunner->calls[ 1 ][ 'command' ]
		);
		$this->assertCount( 1, $setupCoordinator->persistCalls );
	}

	public function testRunCanEnableNoisyDockerOutput() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 0 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => true,
			'needs_build_config' => true,
			'needs_npm_install' => true,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$exitCode = $this->runLaneSilenced( $lane, false, true );
		$this->assertSame( 0, $exitCode );

		foreach ( $dockerComposeExecutor->calls as $call ) {
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
		foreach ( $dockerComposeExecutor->ignoredFailureCalls as $call ) {
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
	}

	public function testNodeVolumeCreateFailureFailsBeforeAssetBuild() :void {
		$processRunner = RecordingProcessRunner::strict( [ 2 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$this->expectExceptionMessage( 'Failed to create labeled source node_modules volume: shield-source-node-modules-test' );

		try {
			$this->runLaneSilenced( $lane, false );
		}
		finally {
			$this->assertCount( 1, $processRunner->calls );
			$this->assertStringContainsString( 'docker volume create', \implode( ' ', $processRunner->calls[ 0 ][ 'command' ] ) );
		}
	}

	public function testRefreshSetupClearsStatePurgesVolumeAndBuildsAssetsOnly() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 0, 0 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$exitCode = $this->runLaneSilenced( $lane, true );
		$this->assertSame( 0, $exitCode );

		$this->assertSame( 1, $setupCoordinator->clearCalls );
		$this->assertCount( 3, $processRunner->calls );
		$this->assertSame(
			[ 'docker', 'volume', 'rm', '-f', 'shield-source-node-modules-test' ],
			$processRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( [ 'docker', 'volume', 'create' ], \array_slice( $processRunner->calls[ 1 ][ 'command' ], 0, 3 ) );
		$this->assertContains( 'com.fernleaf.harness=shield-plugin-source', $processRunner->calls[ 1 ][ 'command' ] );
		$this->assertStringContainsString(
			'npm run build',
			\implode( ' ', $processRunner->calls[ 2 ][ 'command' ] )
		);
		$this->assertStringNotContainsString(
			'npm ci --no-audit --no-fund',
			\implode( ' ', $processRunner->calls[ 2 ][ 'command' ] )
		);
	}

	public function testRefreshSetupFailsWhenNodeVolumePurgeFails() :void {
		$processRunner = RecordingProcessRunner::strict( [
			[ 'exit_code' => 2, 'stderr' => 'volume is in use' ],
		] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$this->expectExceptionMessage( 'Failed to purge source node_modules volume before refresh: shield-source-node-modules-test STDERR: volume is in use' );

		try {
			$this->runLaneSilenced( $lane, true );
		}
		finally {
			$this->assertSame( 1, $setupCoordinator->clearCalls );
			$this->assertCount( 1, $processRunner->calls );
			$this->assertSame(
				[ 'docker', 'volume', 'rm', '-f', 'shield-source-node-modules-test' ],
				$processRunner->calls[ 0 ][ 'command' ]
			);
		}
	}

	public function testLogSinkEnablesOutputCallbacksAndEnvSkipUnitFlagForwarding() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 0 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => true,
			'needs_build_config' => true,
			'needs_npm_install' => true,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$originalLogDir = \getenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
		$hadOriginalLogDir = \is_string( $originalLogDir );
		$originalSkipUnits = \getenv( 'SHIELD_SKIP_UNIT_TESTS' );
		$hadOriginalSkipUnits = \is_string( $originalSkipUnits );
		$logDir = $this->createTrackedTempDir( 'shield-source-runtime-logs-' );
		\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$logDir );
		\putenv( 'SHIELD_SKIP_UNIT_TESTS=1' );

		try {
			$exitCode = $this->runLaneSilenced( $lane, false );
			$this->assertSame( 0, $exitCode );
		}
		finally {
			if ( $hadOriginalLogDir ) {
				\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$originalLogDir );
			}
			else {
				\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
			}
			if ( $hadOriginalSkipUnits ) {
				\putenv( 'SHIELD_SKIP_UNIT_TESTS='.$originalSkipUnits );
			}
			else {
				\putenv( 'SHIELD_SKIP_UNIT_TESTS' );
			}
		}

		$this->assertTrue( $dockerComposeExecutor->calls[ 0 ][ 'has_output_callback' ] );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );

		$runtimeCommands = $this->runtimeRunCommands( $dockerComposeExecutor );
		$this->assertCount( 1, $runtimeCommands );
		foreach ( $runtimeCommands as $runtimeCommand ) {
			$this->assertContains( 'SHIELD_SKIP_UNIT_TESTS=1', $runtimeCommand[ 'sub_command' ] );
		}
		$this->assertFileExists( $logDir.'/runtime-latest.log' );
		$this->assertFileDoesNotExist( $logDir.'/runtime-previous.log' );
	}

	public function testExplicitSkipUnitOptionForwardsSkipUnitFlag() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 0 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0, 0, 0, 0 ] );
		$environmentResolver = $this->createEnvironmentResolver();
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => true,
			'needs_build_config' => true,
			'needs_npm_install' => true,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );

		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$environmentResolver,
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$originalSkipUnits = \getenv( 'SHIELD_SKIP_UNIT_TESTS' );
		$hadOriginalSkipUnits = \is_string( $originalSkipUnits );
		\putenv( 'SHIELD_SKIP_UNIT_TESTS=0' );

		try {
			$exitCode = $this->runLaneSilenced( $lane, false, false, true, true );
			$this->assertSame( 0, $exitCode );
		}
		finally {
			if ( $hadOriginalSkipUnits ) {
				\putenv( 'SHIELD_SKIP_UNIT_TESTS='.$originalSkipUnits );
			}
			else {
				\putenv( 'SHIELD_SKIP_UNIT_TESTS' );
			}
		}

		$runtimeCommands = $this->runtimeRunCommands( $dockerComposeExecutor );
		$this->assertCount( 2, $runtimeCommands );
		foreach ( $runtimeCommands as $runtimeCommand ) {
			$this->assertContains( 'SHIELD_SKIP_UNIT_TESTS=1', $runtimeCommand[ 'sub_command' ] );
		}
	}

	public function testEarlyMysqlFailureCleansDockerEnvAndRestoresPackagePath() :void {
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 7 ] );
		$setupCoordinator = $this->createSetupCoordinator( $this->cacheHitDecision() );
		$capturedEnvLines = [];
		$lane = new SourceRuntimeTestLane(
			RecordingProcessRunner::strict( [] ),
			$this->createEnvWritingResolver( $capturedEnvLines ),
			$dockerComposeExecutor,
			$setupCoordinator
		);
		$originalPackagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		$hadOriginalPackagePath = \is_string( $originalPackagePath );
		\putenv( 'SHIELD_PACKAGE_PATH=/package-from-caller' );

		try {
			$this->assertSame( 1, $this->runLaneSilenced( $lane, false ) );
			$this->assertSame( '/package-from-caller', \getenv( 'SHIELD_PACKAGE_PATH' ) );
		}
		finally {
			if ( $hadOriginalPackagePath ) {
				\putenv( 'SHIELD_PACKAGE_PATH='.$originalPackagePath );
			}
			else {
				\putenv( 'SHIELD_PACKAGE_PATH' );
			}
		}

		$this->assertFileDoesNotExist( $this->projectRoot.'/tests/docker/.env' );
		$this->assertCount( 1, $dockerComposeExecutor->calls );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertCount( 0, $setupCoordinator->persistCalls );
		$this->assertContains( 'WP_VERSION_LATEST=9.4.2', $capturedEnvLines );
		$this->assertContains( 'WP_VERSION_PREVIOUS=9.3.7', $capturedEnvLines );
	}

	public function testComposerSetupFailureShortCircuitsBeforeRuntimeAndCachePersistence() :void {
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 7 ] );
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => true,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => false,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );
		$lane = new SourceRuntimeTestLane(
			RecordingProcessRunner::strict( [] ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$this->assertSame( 1, $this->runLaneSilenced( $lane, false ) );
		$this->assertCount( 3, $dockerComposeExecutor->calls );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertCount( 0, $setupCoordinator->persistCalls );
		$this->assertCount( 0, $this->runtimeRunCommands( $dockerComposeExecutor ) );
	}

	public function testAssetBuildFailureShortCircuitsBeforeRuntimeAndCachePersistence() :void {
		$processRunner = RecordingProcessRunner::strict( [ 0, 7 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0 ] );
		$setupCoordinator = $this->createSetupCoordinator( [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => true,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		] );
		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor,
			$setupCoordinator
		);

		$this->assertSame( 1, $this->runLaneSilenced( $lane, false ) );
		$this->assertCount( 2, $dockerComposeExecutor->calls );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertCount( 0, $setupCoordinator->persistCalls );
		$this->assertCount( 0, $this->runtimeRunCommands( $dockerComposeExecutor ) );
	}

	/**
	 * @dataProvider providerRuntimeFailurePositions
	 * @param int[] $exitCodes
	 */
	public function testRuntimeFailuresAggregateAcrossBothWordpressStreams( array $exitCodes ) :void {
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( $exitCodes );
		$lane = new SourceRuntimeTestLane(
			RecordingProcessRunner::strict( [] ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor,
			$this->createSetupCoordinator( $this->cacheHitDecision() )
		);

		$this->assertSame( 1, $this->runLaneSilenced( $lane, false, false, false, true ) );
		$runtimeCommands = $this->runtimeRunCommands( $dockerComposeExecutor );
		$this->assertCount( 2, $runtimeCommands );
		$this->assertContains( 'test-runner-latest', $runtimeCommands[ 0 ][ 'sub_command' ] );
		$this->assertContains( 'test-runner-previous', $runtimeCommands[ 1 ][ 'sub_command' ] );
	}

	/**
	 * @return array<string,array{0:int[]}>
	 */
	public function providerRuntimeFailurePositions() :array {
		return [
			'latest-fails' => [ [ 0, 0, 7, 0 ] ],
			'previous-fails' => [ [ 0, 0, 0, 7 ] ],
		];
	}

	public function testNodeVolumeExceptionWritesFailingSummary() :void {
		$summaryPath = $this->createTrackedTempFile( 'shield-source-summary-', '.md' );
		$logDir = $this->createTrackedTempDir( 'shield-source-runtime-logs-' );
		$processRunner = RecordingProcessRunner::strict( [ 7 ] );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0 ] );
		$capturedEnvLines = [];
		$lane = new SourceRuntimeTestLane(
			$processRunner,
			$this->createEnvWritingResolver( $capturedEnvLines ),
			$dockerComposeExecutor,
			$this->createSetupCoordinator( [
				'needs_composer_install' => false,
				'needs_build_config' => false,
				'needs_npm_install' => false,
				'needs_npm_build' => true,
				'node_modules_volume' => 'shield-source-node-modules-test',
				'fingerprints' => $this->fingerprints(),
			] )
		);
		$originalLogDir = \getenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
		$originalStepSummary = \getenv( 'GITHUB_STEP_SUMMARY' );
		\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$logDir );
		\putenv( 'GITHUB_STEP_SUMMARY='.$summaryPath );

		try {
			try {
				$this->runLaneSilenced( $lane, false );
				$this->fail( 'Expected node volume creation to fail.' );
			}
			catch ( \RuntimeException $throwable ) {
				$this->assertStringContainsString( 'Failed to create labeled source node_modules volume', $throwable->getMessage() );
			}
			$this->assertStringContainsString( 'Overall Result: FAIL', (string)\file_get_contents( $summaryPath ) );
		}
		finally {
			\putenv( \is_string( $originalLogDir ) ? 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$originalLogDir : 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
			\putenv( \is_string( $originalStepSummary ) ? 'GITHUB_STEP_SUMMARY='.$originalStepSummary : 'GITHUB_STEP_SUMMARY' );
		}

		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertFileDoesNotExist( $this->projectRoot.'/tests/docker/.env' );
	}

	public function testPreflightExceptionWritesFailingArtifactAndSummary() :void {
		$summaryPath = $this->createTrackedTempFile( 'shield-source-summary-', '.md' );
		$logDir = $this->createTrackedTempDir( 'shield-source-runtime-logs-' );
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [] );
		$lane = new SourceRuntimeTestLane(
			RecordingProcessRunner::strict( [] ),
			new class() extends TestingEnvironmentResolver {

				public function assertDockerReady( string $rootDir ) :void {
					throw new \RuntimeException( 'Docker preflight failed.' );
				}
			},
			$dockerComposeExecutor,
			$this->createSetupCoordinator( $this->cacheHitDecision() )
		);
		$originalLogDir = \getenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
		$originalStepSummary = \getenv( 'GITHUB_STEP_SUMMARY' );
		\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$logDir );
		\putenv( 'GITHUB_STEP_SUMMARY='.$summaryPath );

		try {
			try {
				$this->runLaneSilenced( $lane, false );
				$this->fail( 'Expected source preflight to fail.' );
			}
			catch ( \RuntimeException $throwable ) {
				$this->assertSame( 'Docker preflight failed.', $throwable->getMessage() );
			}
		}
		finally {
			\putenv( \is_string( $originalLogDir ) ? 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$originalLogDir : 'SHIELD_SOURCE_RUNTIME_LOG_DIR' );
			\putenv( \is_string( $originalStepSummary ) ? 'GITHUB_STEP_SUMMARY='.$originalStepSummary : 'GITHUB_STEP_SUMMARY' );
		}

		$this->assertSame( [], $dockerComposeExecutor->calls );
		$this->assertSame( [], $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertFileDoesNotExist( $this->projectRoot.'/tests/docker/.env' );
		$this->assertStringContainsString(
			'Exception: Docker preflight failed.',
			(string)\file_get_contents( $logDir.'/preflight.log' )
		);
		$summary = (string)\file_get_contents( $summaryPath );
		$this->assertStringContainsString( 'Overall Result: FAIL', $summary );
		$this->assertStringContainsString(
			'| Prepare source runtime environment | FAIL | 0 | 0 | 1 | `preflight.log` |',
			$summary
		);
	}

	public function testPreflightEnvWriteExceptionRemovesPartialDockerEnv() :void {
		$lane = new SourceRuntimeTestLane(
			RecordingProcessRunner::strict( [] ),
			new class() extends TestingEnvironmentResolver {

				public function assertDockerReady( string $rootDir ) :void {
				}

				public function resolvePhpVersion( string $rootDir ) :string {
					return '8.2';
				}

				public function detectWordpressVersions( string $rootDir ) :array {
					return [ '9.4.2', '9.3.7' ];
				}

				public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
					parent::writeDockerEnvFile( $dockerEnvPath, $lines );
					throw new \RuntimeException( 'Docker env finalization failed.' );
				}
			},
			RecordingDockerComposeExecutor::strict( [] ),
			$this->createSetupCoordinator( $this->cacheHitDecision() )
		);

		try {
			$this->runLaneSilenced( $lane, false );
			$this->fail( 'Expected source preflight env write to fail.' );
		}
		catch ( \RuntimeException $throwable ) {
			$this->assertSame( 'Docker env finalization failed.', $throwable->getMessage() );
		}

		$this->assertFileDoesNotExist( $this->projectRoot.'/tests/docker/.env' );
	}

	/**
	 * @return array<int,array{sub_command:string[]}>
	 */
	private function runtimeRunCommands( RecordingDockerComposeExecutor $dockerComposeExecutor ) :array {
		return \array_values( \array_filter(
			$dockerComposeExecutor->calls,
			static function ( array $call ) :bool {
				return \in_array( 'run', $call[ 'sub_command' ], true )
					&& \in_array( 'SHIELD_SKIP_INNER_SETUP=1', $call[ 'sub_command' ], true )
					&& (
						\in_array( 'test-runner-latest', $call[ 'sub_command' ], true )
						|| \in_array( 'test-runner-previous', $call[ 'sub_command' ], true )
					);
			}
		) );
	}

	private function runLaneSilenced(
		SourceRuntimeTestLane $lane,
		bool $refreshSetup,
		bool $showDockerOutput = false,
		bool $skipUnitTests = false,
		bool $includePreviousWp = false
	) :int {
		\ob_start();
		try {
			return $lane->run(
				$this->projectRoot,
				$refreshSetup,
				$showDockerOutput,
				$skipUnitTests,
				$includePreviousWp
			);
		}
		finally {
			\ob_end_clean();
		}
	}

	private function createEnvironmentResolver() :RecordingTestingEnvironmentResolver {
		return new RecordingTestingEnvironmentResolver();
	}

	/**
	 * @param string[] $capturedEnvLines
	 */
	private function createEnvWritingResolver( array &$capturedEnvLines ) :TestingEnvironmentResolver {
		return new class( $capturedEnvLines ) extends TestingEnvironmentResolver {

			/** @var string[] */
			private array $capturedEnvLines;

			/**
			 * @param string[] $capturedEnvLines
			 */
			public function __construct( array &$capturedEnvLines ) {
				parent::__construct();
				$this->capturedEnvLines = &$capturedEnvLines;
			}

			public function assertDockerReady( string $rootDir ) :void {
			}

			public function resolvePhpVersion( string $rootDir ) :string {
				return '8.2';
			}

			public function detectWordpressVersions( string $rootDir ) :array {
				return [ '9.4.2', '9.3.7' ];
			}

			public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
				$this->capturedEnvLines = $lines;
				parent::writeDockerEnvFile( $dockerEnvPath, $lines );
			}
		};
	}

	/**
	 * @return array{needs_composer_install:bool,needs_build_config:bool,needs_npm_install:bool,needs_npm_build:bool,node_modules_volume:string,fingerprints:array{composer:string,build_config:string,node_deps:string,asset_inputs:string}}
	 */
	private function cacheHitDecision() :array {
		return [
			'needs_composer_install' => false,
			'needs_build_config' => false,
			'needs_npm_install' => false,
			'needs_npm_build' => false,
			'node_modules_volume' => 'shield-source-node-modules-test',
			'fingerprints' => $this->fingerprints(),
		];
	}

	/**
	 * @param array{
	 *   needs_composer_install:bool,
	 *   needs_build_config:bool,
	 *   needs_npm_install:bool,
	 *   needs_npm_build:bool,
	 *   node_modules_volume:string,
	 *   fingerprints:array{composer:string,build_config:string,node_deps:string,asset_inputs:string}
	 * } $decision
	 */
	private function createSetupCoordinator( array $decision ) :RecordingSourceRuntimeSetupCacheCoordinator {
		return new RecordingSourceRuntimeSetupCacheCoordinator( $decision );
	}

	/**
	 * @return array{composer:string,build_config:string,node_deps:string,asset_inputs:string}
	 */
	private function fingerprints() :array {
		return [
			'composer' => 'composer',
			'build_config' => 'build_config',
			'node_deps' => 'node_deps',
			'asset_inputs' => 'asset_inputs',
		];
	}

	/**
	 * @param array<string,string|false> $env
	 */
	private function assertSourceDockerLabels( array $env ) :void {
		$this->assertSame( 'shield-plugin-source', $env[ 'SHIELD_DOCKER_LABEL_HARNESS' ] ?? null );
		$this->assertSame( 'source', $env[ 'SHIELD_DOCKER_LABEL_LANE' ] ?? null );
		$this->assertSame( 'transient', $env[ 'SHIELD_DOCKER_CONTAINER_LIFECYCLE' ] ?? null );
		$this->assertSame( 'reusable', $env[ 'SHIELD_DOCKER_VOLUME_LIFECYCLE' ] ?? null );
		$this->assertMatchesRegularExpression(
			'/^shield-plugin-source-\d{14}-[a-f0-9]{8}$/',
			(string)( $env[ 'SHIELD_DOCKER_CONTAINER_RUN_ID' ] ?? '' )
		);
		$this->assertSame(
			$env[ 'SHIELD_DOCKER_CONTAINER_RUN_ID' ] ?? null,
			$env[ 'SHIELD_DOCKER_VOLUME_RUN_ID' ] ?? null
		);
	}
}
