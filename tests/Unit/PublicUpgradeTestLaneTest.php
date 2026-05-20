<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerComposeExecutor;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradeArtifacts;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageRuntimeLogScanner;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipMetadata;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradePackageZipResolver;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PublicUpgradeTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalSiteProbe;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class PublicUpgradeTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		foreach ( [
			'SHIELD_UPGRADE_TEST_ARTIFACT_DIR',
			'SHIELD_UPGRADE_TEST_COMPOSE_PROJECT',
			'SHIELD_UPGRADE_TEST_SITE_PORT',
		] as $env ) {
			\putenv( $env );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunsNativePublicToCurrentUpgradeSequence() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-lane-root-' );
		$artifactDir = Path::join( $root, 'artifacts' );
		$runner = new RecordingProcessRunner( $this->successfulUpgradeQueue( [ 44, 44 ] ) );
		$docker = new RecordingDockerComposeExecutor( [ 0 ] );
		$lane = $this->buildLane(
			$runner,
			$docker,
			$this->packageResolverReturning( '21.2.7' )
		);
		$this->expectOutputRegex( '#Mode: upgrade-public\r?\nArtifact directory: .+artifacts\r?\n#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PublicUpgradeTestLane::EXIT_PASS, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, 'upgrade-public-summary.json' ) );
		$this->assertSame( 'pass', $summary[ 'status' ] ?? null );
		$this->assertSame( '21.2.7', $summary[ 'final_version' ] ?? null );
		$this->assertSame( 'Updated', $summary[ 'update_result' ][ 'status' ] ?? null );
		$this->assertCommandContains( $runner, 'wp plugin install wp-simple-firewall --activate' );
		$this->assertCommandContains( $runner, 'wp plugin update wp-simple-firewall --format=json' );
		$this->assertCommandContains( $runner, 'wp cron event run --due-now' );
		$this->assertCommandContains( $runner, 'wp eval-file /app/tests/fixtures/upgrade-public/prime-shield-options.php' );
		$this->assertNoCommandContains( $runner, 'plugin install wp-simple-firewall --force' );
		$this->assertSame( '', (string)\file_get_contents( Path::join( $artifactDir, PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE ) ) );
		$this->assertSame( '', (string)\file_get_contents( Path::join( $artifactDir, PublicUpgradeArtifacts::ERROR_EVENTS_FILE ) ) );
		$this->assertSame( [], $this->runtimeLogCopyCommands( $runner ) );
		$this->assertSame( [ 'down', '-v', '--remove-orphans' ], $docker->ignoredFailureCalls[ 0 ][ 'sub_command' ] );
		$this->assertSame( [ 'up', '-d', 'db', 'wordpress' ], $docker->calls[ 0 ][ 'sub_command' ] );
		$this->assertSame( [ 'down', '-v', '--remove-orphans' ], $docker->ignoredFailureCalls[ 1 ][ 'sub_command' ] );
	}

	public function testCopiedRuntimeLogFindingsFailUpgradeLane() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-lane-root-' );
		$artifactDir = Path::join( $root, 'artifacts' );
		$runner = new PublicUpgradeArtifactCopyProcessRunner(
			$this->successfulUpgradeQueue( [ 0, 0, 44 ] ),
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
		$this->expectOutputRegex( '#Mode: upgrade-public[\s\S]+Upgrade public test failed:#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PublicUpgradeTestLane::EXIT_TEST_FAILURE, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, PublicUpgradeArtifacts::SUMMARY_FILE ) );
		$this->assertSame( 'fail', $summary[ 'status' ] ?? null );
		$this->assertNotEmpty( $summary[ 'log_findings' ] ?? [] );
		$this->assertSame( 'shield-scoped-error', $summary[ 'log_findings' ][ 0 ][ 'reason' ] ?? null );
	}

	public function testRuntimeArtifactProbeFailureFailsPassingLane() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-lane-root-' );
		$artifactDir = Path::join( $root, 'artifacts' );
		$runner = new RecordingProcessRunner( $this->successfulUpgradeQueue( [ 9, 44, 44, 0 ] ) );
		$lane = $this->buildLane(
			$runner,
			new RecordingDockerComposeExecutor( [ 0 ] ),
			$this->packageResolverReturning( '21.2.7' )
		);
		$this->expectOutputRegex( '#Mode: upgrade-public[\s\S]+Upgrade public test failed:#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PublicUpgradeTestLane::EXIT_TEST_FAILURE, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, PublicUpgradeArtifacts::SUMMARY_FILE ) );
		$this->assertSame( 'fail', $summary[ 'status' ] ?? null );
		$this->assertStringContainsString( 'Runtime artifact collection failed', (string)( $summary[ 'message' ] ?? '' ) );
	}

	public function testRuntimeArtifactCopyFailureFailsPassingLane() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-lane-root-' );
		$artifactDir = Path::join( $root, 'artifacts' );
		$runner = new RecordingProcessRunner( $this->successfulUpgradeQueue( [ 0, 9, 44, 44, 0 ] ) );
		$lane = $this->buildLane(
			$runner,
			new RecordingDockerComposeExecutor( [ 0 ] ),
			$this->packageResolverReturning( '21.2.7' )
		);
		$this->expectOutputRegex( '#Mode: upgrade-public[\s\S]+Upgrade public test failed:#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PublicUpgradeTestLane::EXIT_TEST_FAILURE, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, PublicUpgradeArtifacts::SUMMARY_FILE ) );
		$this->assertSame( 'fail', $summary[ 'status' ] ?? null );
		$this->assertStringContainsString( 'remote file copy failed', (string)( $summary[ 'message' ] ?? '' ) );
	}

	public function testVersionGateReturnsDeterministicNonPassExitCode() :void {
		$root = $this->createTrackedTempDir( 'shield-upgrade-lane-root-' );
		$artifactDir = Path::join( $root, 'artifacts' );
		$runner = new RecordingProcessRunner( [
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			[ 'exit_code' => 0, 'stdout' => '{"slug":"wp-simple-firewall","version":"21.2.6","download_link":"https://downloads.wordpress.org/plugin/wp-simple-firewall.zip"}' ],
			0,
			[ 'exit_code' => 0, 'stdout' => "21.2.6\n" ],
			9,
			44,
			0,
		] );
		$lane = $this->buildLane(
			$runner,
			new RecordingDockerComposeExecutor( [ 0 ] ),
			$this->packageResolverReturning( '21.2.6' )
		);
		$this->expectOutputRegex( '#Mode: upgrade-public\r?\nArtifact directory: .+artifacts\r?\nUpgrade public version gate: Current package version 21\.2\.6 is not greater than public version 21\.2\.6\.\r?\n#' );

		$exitCode = $lane->run( $root, null, $artifactDir );

		$this->assertSame( PublicUpgradeTestLane::EXIT_VERSION_GATE, $exitCode );
		$summary = $this->decodeJsonFile( Path::join( $artifactDir, 'upgrade-public-summary.json' ) );
		$this->assertSame( 'version-gate', $summary[ 'status' ] ?? null );
		$this->assertSame( PublicUpgradeTestLane::EXIT_VERSION_GATE, $summary[ 'exit_code' ] ?? null );
		$this->assertCommandContains( $runner, 'wp plugin install wp-simple-firewall --activate' );
		$this->assertNoCommandContains( $runner, 'wp plugin update wp-simple-firewall' );
		$this->assertStringContainsString(
			'Runtime artifact collection failed',
			(string)\file_get_contents( Path::join( $artifactDir, PublicUpgradeArtifacts::WP_CLI_LOG_FILE ) )
		);
	}

	private function buildLane(
		RecordingProcessRunner $runner,
		DockerComposeExecutor $docker,
		PublicUpgradePackageZipResolver $packageResolver
	) :PublicUpgradeTestLane {
		return new PublicUpgradeTestLane(
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

	private function successfulUpgradeQueue( array $runtimeArtifactQueue ) :array {
		return \array_merge( [
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			[ 'exit_code' => 0, 'stdout' => '{"slug":"wp-simple-firewall","version":"21.2.6","download_link":"https://downloads.wordpress.org/plugin/wp-simple-firewall.zip"}' ],
			0,
			[ 'exit_code' => 0, 'stdout' => "21.2.6\n" ],
			0,
			0,
			[ 'exit_code' => 0, 'stdout' => '{"ok":true,"plugin":"wp-simple-firewall/icwp-wpsf.php","version":"21.2.7"}' ],
			[ 'exit_code' => 0, 'stdout' => '{"ok":true,"url":"http://wordpress.test/wp-content/uploads/shield-package-runtime-test/wp-simple-firewall-current.zip","status":200}' ],
			[ 'exit_code' => 0, 'stdout' => '{"profile":"strong","applied":["global_enable_plugin_features"],"skipped":[],"excluded":[],"safety_resets":[],"errors":[]}' ],
			[ 'exit_code' => 0, 'stdout' => '[{"name":"wp-simple-firewall","old_version":"21.2.6","new_version":"21.2.7","status":"Updated"}]' ],
			0,
			[ 'exit_code' => 0, 'stdout' => "21.2.7\n" ],
		], $runtimeArtifactQueue );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonFile( string $path ) :array {
		$decoded = \json_decode( (string)\file_get_contents( $path ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	private function assertCommandContains( RecordingProcessRunner $runner, string $fragment ) :void {
		foreach ( $runner->calls as $call ) {
			if ( \strpos( \implode( ' ', $call[ 'command' ] ), $fragment ) !== false ) {
				$this->addToAssertionCount( 1 );
				return;
			}
		}

		$this->fail( 'Command fragment not found: '.$fragment );
	}

	private function assertNoCommandContains( RecordingProcessRunner $runner, string $fragment ) :void {
		foreach ( $runner->calls as $call ) {
			$this->assertStringNotContainsString( $fragment, \implode( ' ', $call[ 'command' ] ) );
		}
	}

	private function runtimeLogCopyCommands( RecordingProcessRunner $runner ) :array {
		return \array_values( \array_filter(
			$runner->calls,
			static function ( array $call ) :bool {
				$command = $call[ 'command' ];
				$cpIndex = \array_search( 'cp', $command, true );
				if ( $cpIndex === false || !isset( $command[ $cpIndex + 1 ] ) ) {
					return false;
				}

				$source = (string)$command[ $cpIndex + 1 ];
				return \str_starts_with( $source, 'wordpress:' )
					   && (
						   \str_contains( $source, PublicUpgradeArtifacts::WORDPRESS_DEBUG_LOG_FILE )
						   || \str_contains( $source, PublicUpgradeArtifacts::ERROR_EVENTS_FILE )
					   );
			}
		) );
	}
}

class PublicUpgradeArtifactCopyProcessRunner extends RecordingProcessRunner {

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
