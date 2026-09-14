<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageFullTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackagePathResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;

class PackageFullTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function setUp() :void {
		parent::setUp();
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunDefaultsToQuietDockerOutput() :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0 ] );
		$capturedEnvLines = [];

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(
				[ 'strauss_version' => null, 'strauss_fork_repo' => null, 'strauss_fork_branch' => null ],
				$capturedEnvLines
			),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 3, $dockerComposeExecutor->calls );
		$this->assertSame(
			[ 'up', '-d', '--wait', '--wait-timeout', '60', 'mysql-latest' ],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertSame(
			[ 'build', 'test-runner-latest' ],
			$dockerComposeExecutor->calls[ 1 ][ 'sub_command' ]
		);
		$this->assertSame(
			[ 'run', '--rm', 'test-runner-latest' ],
			$dockerComposeExecutor->calls[ 2 ][ 'sub_command' ]
		);
		foreach ( $dockerComposeExecutor->calls as $call ) {
			$this->assertFalse( $call[ 'show_docker_output' ] );
		}
		foreach ( $dockerComposeExecutor->ignoredFailureCalls as $call ) {
			$this->assertFalse( $call[ 'show_docker_output' ] );
		}
		$this->assertContains( 'WP_VERSION_LATEST=6.6', $capturedEnvLines );
		$this->assertContains( 'WP_VERSION_PREVIOUS=6.5', $capturedEnvLines );
		$this->assertFileDoesNotExist( $rootDir.'/tests/docker/.env' );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
	}

	public function testRunCanEnableNoisyDockerOutput() :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0 ] );

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir, true );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 3, $dockerComposeExecutor->calls );
		foreach ( $dockerComposeExecutor->calls as $call ) {
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
		foreach ( $dockerComposeExecutor->ignoredFailureCalls as $call ) {
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
	}

	public function testRunCanIncludePreviousWordpress() :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0, 0 ] );

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir, false, true );
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
		$this->assertSame(
			[ 'run', '--rm', 'test-runner-latest' ],
			$dockerComposeExecutor->calls[ 2 ][ 'sub_command' ]
		);
		$this->assertSame(
			[ 'run', '--rm', 'test-runner-previous' ],
			$dockerComposeExecutor->calls[ 3 ][ 'sub_command' ]
		);
	}

	/**
	 * @dataProvider providerRuntimeFailurePositions
	 * @param int[] $exitCodes
	 */
	public function testRuntimeFailuresAggregateAcrossBothWordpressStreams( array $exitCodes ) :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( $exitCodes );
		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor
		);

		$this->assertSame( 1, $this->runLaneSilenced( $lane, $rootDir, false, true ) );
		$this->assertSame( [ 'run', '--rm', 'test-runner-latest' ], $dockerComposeExecutor->calls[ 2 ][ 'sub_command' ] );
		$this->assertSame( [ 'run', '--rm', 'test-runner-previous' ], $dockerComposeExecutor->calls[ 3 ][ 'sub_command' ] );
		$this->assertCount( 2, $dockerComposeExecutor->ignoredFailureCalls );
		$this->assertFileDoesNotExist( $rootDir.'/tests/docker/.env' );
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

	public function testRunWritesForkBranchToDockerEnvWhenForkRepoExists() :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0 ] );
		$capturedEnvLines = [];

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(
				[
					'strauss_version' => '0.19.1',
					'strauss_fork_repo' => 'fernleafsystems/strauss',
					'strauss_fork_branch' => 'feature/packager',
				],
				$capturedEnvLines
			),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir );

		$this->assertSame( 0, $exitCode );
		$this->assertContains( 'SHIELD_STRAUSS_FORK_REPO=fernleafsystems/strauss', $capturedEnvLines );
		$this->assertContains( 'SHIELD_STRAUSS_FORK_BRANCH=feature/packager', $capturedEnvLines );
	}

	public function testRunDoesNotWriteForkBranchToDockerEnvWithoutForkRepo() :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = RecordingDockerComposeExecutor::strict( [ 0, 0, 0 ] );
		$capturedEnvLines = [];

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(
				[
					'strauss_version' => null,
					'strauss_fork_repo' => null,
					'strauss_fork_branch' => 'feature/packager',
				],
				$capturedEnvLines
			),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir );

		$this->assertSame( 0, $exitCode );
		$this->assertNotContains( 'SHIELD_STRAUSS_FORK_BRANCH=feature/packager', $capturedEnvLines );
	}

	public function testPreflightEnvWriteExceptionRemovesPartialDockerEnv() :void {
		$rootDir = $this->createRoot();
		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			new class() extends TestingEnvironmentResolver {

				public function assertDockerReady( string $rootDir ) :void {
				}

				public function resolvePhpVersion( string $rootDir ) :string {
					return '8.2';
				}

				public function detectWordpressVersions( string $rootDir ) :array {
					return [ '9.4.2', '9.3.7' ];
				}

				public function resolvePackagerConfig( string $rootDir ) :array {
					return [
						'strauss_version' => null,
						'strauss_fork_repo' => null,
						'strauss_fork_branch' => null,
					];
				}

				public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
					parent::writeDockerEnvFile( $dockerEnvPath, $lines );
					throw new \RuntimeException( 'Docker env finalization failed.' );
				}
			},
			RecordingDockerComposeExecutor::strict( [] )
		);

		try {
			$this->runLaneSilenced( $lane, $rootDir );
			$this->fail( 'Expected package-full preflight env write to fail.' );
		}
		catch ( \RuntimeException $throwable ) {
			$this->assertSame( 'Docker env finalization failed.', $throwable->getMessage() );
		}

		$this->assertFileDoesNotExist( $rootDir.'/tests/docker/.env' );
	}

	private function runLaneSilenced(
		PackageFullTestLane $lane,
		string $projectRoot,
		bool $showDockerOutput = false,
		bool $includePreviousWp = false
	) :int {
		\ob_start();
		try {
			return $lane->run( $projectRoot, null, $showDockerOutput, $includePreviousWp );
		}
		finally {
			\ob_end_clean();
		}
	}

	private function createRoot() :string {
		$rootDir = $this->createTrackedTempDir( 'shield-package-full-lane-' );
		\mkdir( $rootDir.'/tests/docker', 0777, true );
		return $rootDir;
	}

	private function createPackagePathResolver( string $rootDir ) :PackagePathResolver {
		return new class( $rootDir ) extends PackagePathResolver {

			private string $resolvedPackagePath;

			public function __construct( string $resolvedPackagePath ) {
				parent::__construct();
				$this->resolvedPackagePath = $resolvedPackagePath.'/package';
			}

			public function resolve( string $rootDir, ?string $packagePath = null ) :string {
				return $this->resolvedPackagePath;
			}
		};
	}

	/**
	 * @param array{strauss_version:?string,strauss_fork_repo:?string,strauss_fork_branch:?string} $packagerConfig
	 * @param string[] $capturedEnvLines
	 */
	private function createEnvironmentResolver(
		array $packagerConfig = [ 'strauss_version' => null, 'strauss_fork_repo' => null, 'strauss_fork_branch' => null ],
		array &$capturedEnvLines = []
	) :TestingEnvironmentResolver {
		return new class( $packagerConfig, $capturedEnvLines ) extends TestingEnvironmentResolver {

			/** @var array{strauss_version:?string,strauss_fork_repo:?string,strauss_fork_branch:?string} */
			private array $packagerConfig;

			/** @var string[] */
			private array $capturedEnvLines;

			/**
			 * @param array{strauss_version:?string,strauss_fork_repo:?string,strauss_fork_branch:?string} $packagerConfig
			 * @param string[] $capturedEnvLines
			 */
			public function __construct( array $packagerConfig, array &$capturedEnvLines ) {
				parent::__construct();
				$this->packagerConfig = $packagerConfig;
				$this->capturedEnvLines = &$capturedEnvLines;
			}

			public function resolvePhpVersion( string $rootDir ) :string {
				return '8.2';
			}

			public function assertDockerReady( string $rootDir ) :void {
				unset( $rootDir );
			}

			public function resolvePackagerConfig( string $rootDir ) :array {
				return $this->packagerConfig;
			}

			public function detectWordpressVersions( string $rootDir ) :array {
				return [ '6.6', '6.5' ];
			}

			public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
				$this->capturedEnvLines = $lines;
				parent::writeDockerEnvFile( $dockerEnvPath, $lines );
			}
		};
	}
}
