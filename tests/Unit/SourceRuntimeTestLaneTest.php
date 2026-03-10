<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceRuntimeTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceSetupCacheCoordinator;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
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
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0, 0 ] );
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
		$this->assertCount( 4, $dockerComposeExecutor->calls );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertCount( 0, $processRunner->calls );
		$this->assertCount( 1, $setupCoordinator->persistCalls );
	}

	public function testSetupMissRunsComposerBuildConfigAndNpmInstall() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0, 0, 0, 0 ] );
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

		$this->assertCount( 6, $dockerComposeExecutor->calls );
		$this->assertCount( 1, $processRunner->calls );
		$this->assertStringContainsString(
			'npm ci --no-audit --no-fund && npm run build',
			\implode( ' ', $processRunner->calls[ 0 ][ 'command' ] )
		);
		$this->assertStringContainsString(
			'shield-source-node-modules-test:/app/node_modules',
			\implode( ' ', $processRunner->calls[ 0 ][ 'command' ] )
		);
		$this->assertCount( 1, $setupCoordinator->persistCalls );
	}

	public function testRefreshSetupClearsStatePurgesVolumeAndBuildsAssetsOnly() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0, 0 ] );
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
		$this->assertCount( 2, $processRunner->calls );
		$this->assertSame(
			[ 'docker', 'volume', 'rm', '-f', 'shield-source-node-modules-test' ],
			$processRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertStringContainsString(
			'npm run build',
			\implode( ' ', $processRunner->calls[ 1 ][ 'command' ] )
		);
		$this->assertStringNotContainsString(
			'npm ci --no-audit --no-fund',
			\implode( ' ', $processRunner->calls[ 1 ][ 'command' ] )
		);
	}

	public function testLogSinkEnablesOutputCallbacksAndSkipUnitFlagForwarding() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0, 0, 0, 0 ] );
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
		\putenv( 'SHIELD_SOURCE_RUNTIME_LOG_DIR='.$this->createTrackedTempDir( 'shield-source-runtime-logs-' ) );
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

		$runtimeCommands = \array_values( \array_filter(
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
		$this->assertCount( 2, $runtimeCommands );
		foreach ( $runtimeCommands as $runtimeCommand ) {
			$this->assertContains( 'SHIELD_SKIP_UNIT_TESTS=1', $runtimeCommand[ 'sub_command' ] );
		}
	}

	private function runLaneSilenced( SourceRuntimeTestLane $lane, bool $refreshSetup ) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $refreshSetup );
		}
		finally {
			\ob_end_clean();
		}
	}

	private function createEnvironmentResolver() :TestingEnvironmentResolver {
		return new class() extends TestingEnvironmentResolver {

			public bool $assertDockerReadyCalled = false;

			public function assertDockerReady( string $rootDir ) :void {
				$this->assertDockerReadyCalled = true;
			}

			public function resolvePhpVersion( string $rootDir ) :string {
				return '8.2';
			}

			public function detectWordpressVersions( string $rootDir ) :array {
				return [ '6.9', '6.8.3' ];
			}

			public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
			}
		};
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
	private function createSetupCoordinator( array $decision ) :SourceSetupCacheCoordinator {
		return new class( $decision ) extends SourceSetupCacheCoordinator {

			/** @var array{
			 *   needs_composer_install:bool,
			 *   needs_build_config:bool,
			 *   needs_npm_install:bool,
			 *   needs_npm_build:bool,
			 *   node_modules_volume:string,
			 *   fingerprints:array{composer:string,build_config:string,node_deps:string,asset_inputs:string}
			 * } */
			private array $decision;

			/** @var array<int,array<string,mixed>> */
			public array $persistCalls = [];

			public int $clearCalls = 0;

			public function __construct( array $decision ) {
				$this->decision = $decision;
			}

			public function clearState( string $rootDir ) :void {
				$this->clearCalls++;
			}

			public function evaluateRuntimeSetup( string $rootDir, string $phpVersion, bool $refreshSetup = false ) :array {
				return $this->decision;
			}

			public function persistRuntimeSetupState( string $rootDir, array $fingerprints ) :void {
				$this->persistCalls[] = [
					'root_dir' => $rootDir,
					'fingerprints' => $fingerprints,
				];
			}

			public function getNodeModulesVolumeName( string $rootDir ) :string {
				return $this->decision[ 'node_modules_volume' ];
			}
		};
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
}
