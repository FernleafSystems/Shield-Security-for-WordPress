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
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0, 0 ] );

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 4, $dockerComposeExecutor->calls );
		foreach ( $dockerComposeExecutor->calls as $call ) {
			$this->assertFalse( $call[ 'show_docker_output' ] );
		}
	}

	public function testRunCanEnableNoisyDockerOutput() :void {
		$rootDir = $this->createRoot();
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0, 0 ] );

		$lane = new PackageFullTestLane(
			null,
			$this->createPackagePathResolver( $rootDir ),
			$this->createEnvironmentResolver(),
			$dockerComposeExecutor
		);

		$exitCode = $this->runLaneSilenced( $lane, $rootDir, true );
		$this->assertSame( 0, $exitCode );
		$this->assertCount( 4, $dockerComposeExecutor->calls );
		foreach ( $dockerComposeExecutor->calls as $call ) {
			$this->assertTrue( $call[ 'show_docker_output' ] );
		}
	}

	private function runLaneSilenced(
		PackageFullTestLane $lane,
		string $projectRoot,
		bool $showDockerOutput = false
	) :int {
		\ob_start();
		try {
			return $lane->run( $projectRoot, null, $showDockerOutput );
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

	private function createEnvironmentResolver() :TestingEnvironmentResolver {
		return new class extends TestingEnvironmentResolver {

			public function resolvePhpVersion( string $rootDir ) :string {
				return '8.2';
			}

			public function resolvePackagerConfig( string $rootDir ) :array {
				return [
					'strauss_version' => null,
					'strauss_fork_repo' => null,
				];
			}

			public function detectWordpressVersions( string $rootDir ) :array {
				return [ '6.6', '6.5' ];
			}
		};
	}
}
