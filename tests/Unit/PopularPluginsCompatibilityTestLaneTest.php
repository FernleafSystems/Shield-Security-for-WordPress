<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerComposeExecutor;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PopularPluginsCompatibilityArtifacts;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PopularPluginsCompatibilityTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageRuntimeLogScanner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipMetadata;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalSiteProbe;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class PopularPluginsCompatibilityTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		foreach ( [
			'SHIELD_POPULAR_PLUGIN_TEST_ARTIFACT_DIR',
			'SHIELD_POPULAR_PLUGIN_TEST_COMPOSE_PROJECT',
			'SHIELD_POPULAR_PLUGIN_TEST_SITE_PORT',
		] as $env ) {
			\putenv( $env );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testInstallsAndActivatesPinnedCompanionsBeforeShieldPackage() :void {
		$root = \dirname( __DIR__, 2 );
		$artifactDir = $this->createTrackedTempDir( 'shield-popular-artifacts-' );
		$runner = new FastPopularPluginProcessRunner( $this->successfulCompatibilityQueue() );
		$lane = $this->buildLane(
			$runner,
			new RecordingDockerComposeExecutor( [ 0 ] ),
			$this->packageResolverReturning( '21.2.7' )
		);
		$this->expectOutputRegex( '#Mode: popular-plugins\r?\nArtifact directory: .+#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PopularPluginsCompatibilityTestLane::EXIT_PASS, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, PopularPluginsCompatibilityArtifacts::SUMMARY_FILE ) );
		$this->assertSame( 'pass', $summary[ 'status' ] ?? null );
		$this->assertSame( 20, $summary[ 'companion_plugin_count' ] ?? null );
		$this->assertSame( [], $summary[ 'log_findings' ] ?? null );
		$this->assertCommandContains( $runner, 'wp plugin install wordfence' );
		$this->assertCommandContains( $runner, 'wp plugin activate wordfence' );
		$this->assertCommandContains( $runner, 'wp plugin install easy-digital-downloads' );
		$this->assertCommandContains( $runner, 'wp plugin activate easy-digital-downloads' );
		$this->assertCommandContains( $runner, 'wp plugin install /var/www/html/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip --activate' );
		$this->assertCommandContains( $runner, 'wp plugin list --status=active --format=json' );
		$this->assertCommandContains( $runner, 'wp cron event run --due-now' );
		$this->assertCompanionsPrecedeShieldInstall( $runner );
		$this->assertFileExists( Path::join( $artifactDir, PopularPluginsCompatibilityArtifacts::COMPANION_PLUGINS_FILE ) );
		$this->assertFileExists( Path::join( $artifactDir, PopularPluginsCompatibilityArtifacts::ACTIVATION_RESULTS_FILE ) );
	}

	public function testBaselineFatalExitsBeforeShieldPackageInstall() :void {
		$root = \dirname( __DIR__, 2 );
		$artifactDir = $this->createTrackedTempDir( 'shield-popular-artifacts-' );
		$runner = new PopularPluginArtifactCopyProcessRunner(
			$this->queueThroughBaselineCollection( [ 0, 0, 44 ] ),
			[
				'wordpress:/var/www/html/wp-content/shield-runtime-test/wordpress-debug.log'
					=> 'PHP Fatal error: Allowed memory size exhausted'.\PHP_EOL,
			]
		);
		$lane = $this->buildLane(
			$runner,
			new RecordingDockerComposeExecutor( [ 0 ] ),
			$this->packageResolverReturning( '21.2.7' )
		);
		$this->expectOutputRegex( '#Mode: popular-plugins[\s\S]+Popular plugin baseline failed:#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PopularPluginsCompatibilityTestLane::EXIT_BASELINE_FAILURE, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, PopularPluginsCompatibilityArtifacts::SUMMARY_FILE ) );
		$this->assertSame( 'baseline-fail', $summary[ 'status' ] ?? null );
		$this->assertSame( 'global-fatal', $summary[ 'baseline_log_findings' ][ 0 ][ 'reason' ] ?? null );
		$this->assertNoCommandContains( $runner, 'wp plugin install /var/www/html/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip --activate' );
	}

	public function testFinalShieldScopedLogFindingFailsCompatibilityLane() :void {
		$root = \dirname( __DIR__, 2 );
		$artifactDir = $this->createTrackedTempDir( 'shield-popular-artifacts-' );
		$runner = new PopularPluginArtifactCopyProcessRunner(
			$this->successfulCompatibilityQueue( [ 0, 0, 44 ] ),
			[
				'wordpress:/var/www/html/wp-content/shield-runtime-test/wordpress-debug.log'
					=> 'PHP Deprecated: Thing in wp-content/plugins/wp-simple-firewall/icwp-wpsf.php'.\PHP_EOL,
			]
		);
		$lane = $this->buildLane(
			$runner,
			new RecordingDockerComposeExecutor( [ 0 ] ),
			$this->packageResolverReturning( '21.2.7' )
		);
		$this->expectOutputRegex( '#Mode: popular-plugins[\s\S]+Popular plugin compatibility test failed:#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PopularPluginsCompatibilityTestLane::EXIT_TEST_FAILURE, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, PopularPluginsCompatibilityArtifacts::SUMMARY_FILE ) );
		$this->assertSame( 'fail', $summary[ 'status' ] ?? null );
		$this->assertSame( 'shield-scoped-error', $summary[ 'log_findings' ][ 0 ][ 'reason' ] ?? null );
	}

	private function buildLane(
		FastPopularPluginProcessRunner $runner,
		DockerComposeExecutor $docker,
		PublicUpgradePackageZipResolver $packageResolver
	) :PopularPluginsCompatibilityTestLane {
		return new PopularPluginsCompatibilityTestLane(
			$runner,
			new RecordingTestingEnvironmentResolver(),
			$docker,
			new RecordingLocalSiteProbe( [ true ], [ true ], [ false ] ),
			$packageResolver,
			new PackageRuntimeLogScanner()
		);
	}

	private function packageResolverReturning( string $version ) :PublicUpgradePackageZipResolver {
		$resolver = $this->createMock( PublicUpgradePackageZipResolver::class );
		$resolver->method( 'resolve' )
				 ->willReturn( new PublicUpgradePackageZipMetadata(
					 'builds/wp-simple-firewall.zip',
					 $version,
					 'wp-simple-firewall/icwp-wpsf.php'
				 ) );
		return $resolver;
	}

	/**
	 * @param array<int,int|array{exit_code:int,stdout?:string,stderr?:string}> $finalRuntimeArtifactQueue
	 * @return array<int,int|array{exit_code:int,stdout?:string,stderr?:string}>
	 */
	private function successfulCompatibilityQueue( array $finalRuntimeArtifactQueue = [ 44, 44 ] ) :array {
		return \array_merge(
			$this->queueThroughBaselineCollection( [ 44, 44 ] ),
			[
				0,
				0,
				0,
				[ 'exit_code' => 0, 'stdout' => "21.2.7\n" ],
				[ 'exit_code' => 0, 'stdout' => '[{"name":"wp-simple-firewall","status":"active"}]' ],
				0,
				0,
			],
			$finalRuntimeArtifactQueue
		);
	}

	/**
	 * @param array<int,int|array{exit_code:int,stdout?:string,stderr?:string}> $baselineRuntimeArtifactQueue
	 * @return array<int,int|array{exit_code:int,stdout?:string,stderr?:string}>
	 */
	private function queueThroughBaselineCollection( array $baselineRuntimeArtifactQueue ) :array {
		return \array_merge(
			\array_fill( 0, 10, 0 ),
			\array_fill( 0, 40, 0 ),
			$baselineRuntimeArtifactQueue
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonFile( string $path ) :array {
		$decoded = \json_decode( (string)\file_get_contents( $path ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	private function assertCommandContains( FastPopularPluginProcessRunner $runner, string $fragment ) :void {
		foreach ( $runner->calls as $call ) {
			if ( \strpos( \implode( ' ', $call[ 'command' ] ), $fragment ) !== false ) {
				$this->addToAssertionCount( 1 );
				return;
			}
		}

		$this->fail( 'Command fragment not found: '.$fragment );
	}

	private function assertNoCommandContains( FastPopularPluginProcessRunner $runner, string $fragment ) :void {
		foreach ( $runner->calls as $call ) {
			$this->assertStringNotContainsString( $fragment, \implode( ' ', $call[ 'command' ] ) );
		}
	}

	private function assertCompanionsPrecedeShieldInstall( FastPopularPluginProcessRunner $runner ) :void {
		$wordfenceActivation = null;
		$shieldInstall = null;
		foreach ( $runner->calls as $index => $call ) {
			$command = \implode( ' ', $call[ 'command' ] );
			if ( \strpos( $command, 'wp plugin activate wordfence' ) !== false ) {
				$wordfenceActivation = $index;
			}
			if ( \strpos( $command, 'wp plugin install /var/www/html/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip --activate' ) !== false ) {
				$shieldInstall = $index;
			}
		}

		$this->assertIsInt( $wordfenceActivation );
		$this->assertIsInt( $shieldInstall );
		$this->assertLessThan( $shieldInstall, $wordfenceActivation );
	}
}

class FastPopularPluginProcessRunner extends ProcessRunner {

	/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
	public array $calls = [];

	/**
	 * @var array<int,int|array{exit_code:int,stdout?:string,stderr?:string}>
	 */
	private array $exitCodes;

	/**
	 * @param array<int,int|array{exit_code:int,stdout?:string,stderr?:string}> $exitCodes
	 */
	public function __construct( array $exitCodes = [ 0 ] ) {
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
			'has_output_callback' => $onOutput !== null,
		];

		$queueEntry = \array_shift( $this->exitCodes );
		$exitCode = \is_array( $queueEntry ) ? (int)( $queueEntry[ 'exit_code' ] ?? 0 ) : (int)( $queueEntry ?? 0 );
		$stdout = \is_array( $queueEntry ) ? (string)( $queueEntry[ 'stdout' ] ?? '' ) : '';
		$stderr = \is_array( $queueEntry ) ? (string)( $queueEntry[ 'stderr' ] ?? '' ) : '';
		if ( $onOutput !== null ) {
			if ( $stdout !== '' ) {
				$onOutput( Process::OUT, $stdout );
			}
			if ( $stderr !== '' ) {
				$onOutput( Process::ERR, $stderr );
			}
		}

		return new FastPopularPluginProcess( $exitCode, $stdout, $stderr );
	}
}

class FastPopularPluginProcess extends Process {

	private int $recordedExitCode;

	private string $recordedOutput;

	private string $recordedErrorOutput;

	public function __construct( int $exitCode, string $output = '', string $errorOutput = '' ) {
		parent::__construct( [ \PHP_BINARY, '-r', 'exit(0);' ] );
		$this->recordedExitCode = $exitCode;
		$this->recordedOutput = $output;
		$this->recordedErrorOutput = $errorOutput;
	}

	public function getExitCode() :?int {
		return $this->recordedExitCode;
	}

	public function getOutput() :string {
		return $this->recordedOutput;
	}

	public function getErrorOutput() :string {
		return $this->recordedErrorOutput;
	}
}

class PopularPluginArtifactCopyProcessRunner extends FastPopularPluginProcessRunner {

	private array $copiedArtifacts;

	public function __construct( array $exitCodes, array $copiedArtifacts ) {
		parent::__construct( $exitCodes );
		$this->copiedArtifacts = $copiedArtifacts;
	}

	public function run(
		array $command,
		string $workingDir,
		?callable $onOutput = null,
		?array $envOverrides = null
	) :Process {
		$process = parent::run( $command, $workingDir, $onOutput, $envOverrides );
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			return $process;
		}

		$cpIndex = \array_search( 'cp', $command, true );
		if ( $cpIndex === false || !isset( $command[ $cpIndex + 1 ], $command[ $cpIndex + 2 ] ) ) {
			return $process;
		}

		$source = (string)$command[ $cpIndex + 1 ];
		$target = (string)$command[ $cpIndex + 2 ];
		if ( \array_key_exists( $source, $this->copiedArtifacts ) ) {
			\file_put_contents( $target, $this->copiedArtifacts[ $source ] );
		}

		return $process;
	}
}
