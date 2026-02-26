<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackagePathResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageTargetedTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PackageTargetedTestLaneTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	public function testFailOnSkippedTrueForcesStrictSkipArg() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0, 0 ] );
		$lane = $this->createLane( $processRunner, '/resolved/package' );

		$exitCode = $this->runLaneSilenced( $lane, null, true );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertContains( '--fail-on-skipped', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertContains( '--fail-on-skipped', $processRunner->calls[ 1 ][ 'command' ] );
	}

	public function testFailOnSkippedFalseRemovesStrictSkipArg() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0, 0 ] );
		$lane = $this->createLane( $processRunner, '/resolved/package' );

		$exitCode = $this->runLaneSilenced( $lane, null, false );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertNotContains( '--fail-on-skipped', $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertNotContains( '--fail-on-skipped', $processRunner->calls[ 1 ][ 'command' ] );
	}

	public function testFailOnSkippedAutoMatchesPlatformDefault() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0, 0 ] );
		$lane = $this->createLane( $processRunner, '/resolved/package' );

		$exitCode = $this->runLaneSilenced( $lane, null, null );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $processRunner->calls );

		$expectsStrictSkip = \PHP_OS_FAMILY !== 'Windows';
		if ( $expectsStrictSkip ) {
			$this->assertContains( '--fail-on-skipped', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertContains( '--fail-on-skipped', $processRunner->calls[ 1 ][ 'command' ] );
		}
		else {
			$this->assertNotContains( '--fail-on-skipped', $processRunner->calls[ 0 ][ 'command' ] );
			$this->assertNotContains( '--fail-on-skipped', $processRunner->calls[ 1 ][ 'command' ] );
		}
	}

	public function testRunPassesPackageEnvOverridesPerProcessWithoutGlobalMutation() :void {
		$processRunner = $this->createRecordingProcessRunner( [ 0, 0 ] );
		$packagePath = '/resolved/package';
		$lane = $this->createLane(
			$processRunner,
			$packagePath,
			[
				'strauss_version' => '0.19.1',
				'strauss_fork_repo' => 'fernleafsystems/strauss',
			]
		);

		$originalPackagePath = \getenv( 'SHIELD_PACKAGE_PATH' );
		$hadOriginalPackagePath = \is_string( $originalPackagePath );
		\putenv( 'SHIELD_PACKAGE_PATH=host-value' );

		try {
			$exitCode = $this->runLaneSilenced( $lane, null, false );
			$this->assertSame( 0, $exitCode );
			$this->assertCount( 2, $processRunner->calls );

			foreach ( $processRunner->calls as $call ) {
				$this->assertSame(
					[
						'SHIELD_PACKAGE_PATH' => $packagePath,
						'SHIELD_STRAUSS_VERSION' => '0.19.1',
						'SHIELD_STRAUSS_FORK_REPO' => 'fernleafsystems/strauss',
					],
					$call[ 'env_overrides' ]
				);
			}
			$this->assertSame( 'host-value', \getenv( 'SHIELD_PACKAGE_PATH' ) );
		}
		finally {
			if ( $hadOriginalPackagePath ) {
				\putenv( 'SHIELD_PACKAGE_PATH='.$originalPackagePath );
			}
			else {
				\putenv( 'SHIELD_PACKAGE_PATH' );
			}
		}
	}

	private function runLaneSilenced(
		PackageTargetedTestLane $lane,
		?string $packagePath = null,
		?bool $failOnSkipped = null
	) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $packagePath, $failOnSkipped );
		}
		finally {
			\ob_end_clean();
		}
	}

	/**
	 * @param array{strauss_version:?string,strauss_fork_repo:?string} $packagerConfig
	 */
	private function createLane(
		ProcessRunner $processRunner,
		string $resolvedPackagePath,
		array $packagerConfig = [ 'strauss_version' => null, 'strauss_fork_repo' => null ]
	) :PackageTargetedTestLane {
		$packagePathResolver = new class( $resolvedPackagePath ) extends PackagePathResolver {

			private string $resolvedPackagePath;

			public function __construct( string $resolvedPackagePath ) {
				parent::__construct();
				$this->resolvedPackagePath = $resolvedPackagePath;
			}

			public function resolve( string $rootDir, ?string $packagePath = null ) :string {
				return $this->resolvedPackagePath;
			}
		};

		$environmentResolver = new class( $packagerConfig ) extends TestingEnvironmentResolver {

			/** @var array{strauss_version:?string,strauss_fork_repo:?string} */
			private array $packagerConfig;

			/**
			 * @param array{strauss_version:?string,strauss_fork_repo:?string} $packagerConfig
			 */
			public function __construct( array $packagerConfig ) {
				parent::__construct();
				$this->packagerConfig = $packagerConfig;
			}

			/**
			 * @return array{strauss_version:?string,strauss_fork_repo:?string}
			 */
			public function resolvePackagerConfig( string $rootDir ) :array {
				return $this->packagerConfig;
			}
		};

		return new PackageTargetedTestLane( $processRunner, $packagePathResolver, $environmentResolver );
	}

	/**
	 * @param int[] $exitCodes
	 */
	private function createRecordingProcessRunner( array $exitCodes ) :ProcessRunner {
		return new class( $exitCodes ) extends ProcessRunner {

			/** @var array<int,array{command:array,working_dir:string,env_overrides:?array}> */
			public array $calls = [];

			/** @var int[] */
			private array $exitCodes;

			/**
			 * @param int[] $exitCodes
			 */
			public function __construct( array $exitCodes ) {
				parent::__construct();
				$this->exitCodes = $exitCodes;
			}

			public function run(
				array $command,
				string $workingDir,
				?callable $onOutput = null,
				?array $envOverrides = null
			) :Process {
				$this->calls[] = [
					'command' => $command,
					'working_dir' => $workingDir,
					'env_overrides' => $envOverrides,
				];

				$exitCode = \array_shift( $this->exitCodes );
				$process = new Process(
					[
						\PHP_BINARY,
						'-r',
						'exit('.(int)( $exitCode ?? 0 ).');',
					]
				);
				$process->run( static function () :void {
				} );

				return $process;
			}
		};
	}
}
