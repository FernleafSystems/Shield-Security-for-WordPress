<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalSiteProbe;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalSiteRuntimeRefresher;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalSiteManagerTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-local-site-' );
		$this->seedRequiredFiles( $this->projectRoot );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testEnsureReadyStartsAndProvisionsWhenSiteIsNotRunning() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$probe = new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$manager->ensureReady( $this->projectRoot, true );

		$this->assertCount( 1, $dockerComposeExecutor->calls );
		$this->assertSame(
			[ 'up', '-d', 'db', 'wordpress' ],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertSame( 'shield-local-site', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'COMPOSE_PROJECT_NAME' ] );
		$this->assertSame( '8.2', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'PHP_VERSION' ] );

		$this->assertCount( 2, $runtimeRefresher->resolveCalls );
		$this->assertCount( 1, $runtimeRefresher->refreshCalls );
		$this->assertSame( 'wordpress-container', $runtimeRefresher->refreshCalls[ 0 ][ 'container_id' ] );
		$this->assertCount( 2, $processRunner->calls );
		$this->assertSame( [ \PHP_BINARY, './bin/build-config.php' ], $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertSame( 'docker', $processRunner->calls[ 1 ][ 'command' ][ 0 ] );
		$this->assertContains( 'wp-cli', $processRunner->calls[ 1 ][ 'command' ] );
	}

	public function testEnsureReadyPassesTestProfileProvisioningMetadata() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$probe = new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::test(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$manager->ensureReady( $this->projectRoot, true );

		$this->assertContains( 'SHIELD_LOCAL_SITE_PROFILE=test', $processRunner->calls[ 1 ][ 'command' ] );
		$this->assertContains( 'SHIELD_LOCAL_SITE_TITLE=Shield Local Test Site', $processRunner->calls[ 1 ][ 'command' ] );
	}

	public function testEnsureReadyReusesHealthySiteAndOnlyRunsProvisioning() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalSiteProbe( [ true, true ], [ true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$manager->ensureReady( $this->projectRoot, false );

		$this->assertCount( 0, $dockerComposeExecutor->calls );
		$this->assertCount( 1, $runtimeRefresher->refreshCalls );
		$this->assertCount( 2, $processRunner->calls );
	}

	public function testWpEnsuresReadyThenRunsWpCliPassthrough() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalSiteProbe( [ true, true ], [ true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$exitCode = $manager->wp( $this->projectRoot, [ 'plugin', 'list' ] );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $runtimeRefresher->refreshCalls );
		$this->assertCount( 3, $processRunner->calls );
		$this->assertSame(
			[
				'docker',
				'compose',
				'-f',
				'tests/docker/docker-compose.local-site.yml',
				'run',
				'--rm',
				'-T',
				'wp-cli',
				'wp',
				'plugin',
				'list',
				'--allow-root',
			],
			$processRunner->calls[ 2 ][ 'command' ]
		);
	}

	public function testWpCaptureReturnsCommandStdoutAndRoutesSetupNoiseToStderr() :void {
		$processRunner = new RecordingProcessRunner( [
			[
				'exit_code' => 0,
			],
			[
				'exit_code' => 0,
				'stdout' => "provisioning\n",
			],
			[
				'exit_code' => 0,
				'stdout' => "{\"ok\":true}\n",
				'stderr' => "fixture-warning\n",
			],
		] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalSiteProbe( [ true, true ], [ true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$captured = $manager->wpCapture( $this->projectRoot, [ 'eval', 'return wp_json_encode(["ok" => true]);' ] );

		$this->assertSame( "{\"ok\":true}\n", $captured[ 'stdout' ] );
		$this->assertStringContainsString( 'provisioning', $captured[ 'stderr' ] );
		$this->assertStringContainsString( 'fixture-warning', $captured[ 'stderr' ] );
		$this->assertCount( 3, $processRunner->calls );
		$this->assertFalse( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
		$this->assertTrue( $processRunner->calls[ 1 ][ 'has_output_callback' ] );
		$this->assertTrue( $processRunner->calls[ 2 ][ 'has_output_callback' ] );
	}

	public function testEnsureReadyFailsFastWhenReusedSiteIsUnhealthyBeforeRefresh() :void {
		$this->expectExceptionMessage( 'Local dev site is already running but unhealthy before runtime refresh.' );

		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalSiteProbe( [ false ], [ true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		try {
			$manager->ensureReady( $this->projectRoot, false );
		}
		finally {
			$this->assertCount( 0, $dockerComposeExecutor->calls );
			$this->assertCount( 0, $runtimeRefresher->refreshCalls );
			$this->assertCount( 1, $processRunner->calls );
		}
	}

	public function testEnsureReadyFailsFastWhenSiteIsUnhealthyAfterRefresh() :void {
		$this->expectExceptionMessage( 'Local dev site is unhealthy after runtime refresh.' );

		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalSiteProbe( [ true ], [ false ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		try {
			$manager->ensureReady( $this->projectRoot, false );
		}
		finally {
			$this->assertCount( 1, $runtimeRefresher->refreshCalls );
			$this->assertCount( 1, $processRunner->calls );
		}
	}

	public function testEnsureReadyFailsFastOnPortConflict() :void {
		$this->expectExceptionMessage( 'Port 8888 is already in use' );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			new RecordingProcessRunner(),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor(),
			new RecordingLocalSiteProbe( [ false ], [ true ], [ true ] ),
			new RecordingLocalSiteRuntimeRefresher( [ '' ] )
		);

		$manager->ensureReady( $this->projectRoot, false );
	}

	public function testEnsureReadyFailsFastWhenRequiredArtifactsAreMissing() :void {
		$this->expectExceptionMessage( 'Compiled assets are missing' );
		$this->cleanupTrackedTempDirs();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-local-site-missing-' );
		\mkdir( Path::join( $this->projectRoot, 'vendor' ), 0777, true );
		\file_put_contents( Path::join( $this->projectRoot, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents( Path::join( $this->projectRoot, 'plugin.json' ), '{}' );
		\file_put_contents( Path::join( $this->projectRoot, 'icwp-wpsf.php' ), '<?php' );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			new RecordingProcessRunner(),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor(),
			new RecordingLocalSiteProbe(),
			new RecordingLocalSiteRuntimeRefresher( [ '' ] )
		);

		$manager->ensureReady( $this->projectRoot, false );
	}

	public function testEnsureReadyFailsFastWhenSiteIsUnhealthyAfterProvisioning() :void {
		$this->expectExceptionMessage( 'Local dev site is unhealthy after provisioning.' );

		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalSiteProbe( [ true, false ], [ true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		try {
			$manager->ensureReady( $this->projectRoot, false );
		}
		finally {
			$this->assertCount( 1, $runtimeRefresher->refreshCalls );
			$this->assertCount( 2, $processRunner->calls );
		}
	}

	public function testResetDestroysStateAndReprovisions() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0 ] );
		$probe = new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$exitCode = $manager->reset( $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $dockerComposeExecutor->calls );
		$this->assertSame( [ 'down', '-v', '--remove-orphans' ], $dockerComposeExecutor->calls[ 0 ][ 'sub_command' ] );
		$this->assertSame( [ 'up', '-d', 'db', 'wordpress' ], $dockerComposeExecutor->calls[ 1 ][ 'sub_command' ] );
	}

	public function testResetFailsFastWhenBrowserPrerequisitesAreMissing() :void {
		$this->expectExceptionMessage( 'Playwright is not installed' );
		$this->cleanupTrackedTempDirs();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-local-site-browser-missing-' );
		\mkdir( Path::join( $this->projectRoot, 'vendor' ), 0777, true );
		\mkdir( Path::join( $this->projectRoot, 'assets', 'dist' ), 0777, true );
		\file_put_contents( Path::join( $this->projectRoot, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents( Path::join( $this->projectRoot, 'plugin.json' ), '{}' );
		\file_put_contents( Path::join( $this->projectRoot, 'icwp-wpsf.php' ), '<?php' );

		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0 ] );
		$manager = new LocalSiteManager(
			LocalSiteDefinitions::test(),
			new RecordingProcessRunner( [ 0 ] ),
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] ),
			new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] )
		);

		try {
			$manager->reset( $this->projectRoot, true );
		}
		finally {
			$this->assertCount( 0, $dockerComposeExecutor->calls );
		}
	}

	public function testTestSiteUsesDistinctProjectNamePortAndDbName() :void {
		$processRunner = new RecordingProcessRunner( [ 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$probe = new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] );
		$runtimeRefresher = new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::test(),
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe,
			$runtimeRefresher
		);

		$manager->ensureReady( $this->projectRoot, false );

		$this->assertSame( 'shield-test-site', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'COMPOSE_PROJECT_NAME' ] );
		$this->assertSame( '8889', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_LOCAL_SITE_PORT' ] );
		$this->assertSame( 'shield_test_site', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_LOCAL_SITE_DB_NAME' ] );
	}

	public function testEnsureReadyFailsFastWhenSourceMetadataRemainsOutOfSync() :void {
		$this->writeMetadataFiles( $this->projectRoot, '21.99.3', '21.99.3', '21.99.4', '202604.1701', '202604.1801' );
		$this->expectExceptionMessage( 'Generated plugin.json is out of sync with plugin-spec/01_properties.json' );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			new RecordingProcessRunner( [ 0 ] ),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor(),
			new RecordingLocalSiteProbe(),
			new RecordingLocalSiteRuntimeRefresher( [ '' ] )
		);

		$manager->ensureReady( $this->projectRoot, false );
	}

	public function testEnsureReadyFailsFastWhenPluginHeaderRemainsOutOfSync() :void {
		$this->writeMetadataFiles( $this->projectRoot, '21.99.4', '21.99.3', '21.99.3', '202604.1701', '202604.1701' );
		$this->expectExceptionMessage( 'Generated plugin.json and icwp-wpsf.php plugin header are out of sync' );

		$manager = new LocalSiteManager(
			LocalSiteDefinitions::dev(),
			new RecordingProcessRunner( [ 0 ] ),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor(),
			new RecordingLocalSiteProbe(),
			new RecordingLocalSiteRuntimeRefresher( [ '' ] )
		);

		$manager->ensureReady( $this->projectRoot, false );
	}

	private function seedRequiredFiles( string $rootDir ) :void {
		\mkdir( Path::join( $rootDir, 'vendor' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'assets', 'dist' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'node_modules', '@playwright', 'test' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'plugin-spec' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'bin' ), 0777, true );
		\file_put_contents( Path::join( $rootDir, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'bin', 'build-config.php' ), '<?php' );
		$this->writeMetadataFiles( $rootDir, '21.99.3', '21.99.3', '21.99.3', '202604.1701', '202604.1701' );
		\file_put_contents( Path::join( $rootDir, 'node_modules', '@playwright', 'test', 'cli.js' ), '' );
	}

	private function writeMetadataFiles(
		string $rootDir,
		string $headerVersion,
		string $sourceVersion,
		string $configVersion,
		string $sourceBuild,
		string $configBuild
	) :void {
		\file_put_contents(
			Path::join( $rootDir, 'plugin-spec', '01_properties.json' ),
			\json_encode( [
				'version' => $sourceVersion,
				'build' => $sourceBuild,
			], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '{}'
		);
		\file_put_contents(
			Path::join( $rootDir, 'plugin.json' ),
			\json_encode( [
				'properties' => [
					'version' => $configVersion,
					'build' => $configBuild,
				],
			], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '{}'
		);
		\file_put_contents(
			Path::join( $rootDir, 'icwp-wpsf.php' ),
			"<?php\n/*\n * Version: {$headerVersion}\n */\n"
		);
	}
}
