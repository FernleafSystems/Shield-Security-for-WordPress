<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalDevSiteProbe;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingTestingEnvironmentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalDevSiteManagerTest extends TestCase {

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
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$probe = new RecordingLocalDevSiteProbe( [ false ], [ true ], [ false ] );

		$manager = new LocalDevSiteManager(
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe
		);

		$manager->ensureReady( $this->projectRoot, true );

		$this->assertCount( 1, $dockerComposeExecutor->calls );
		$this->assertSame(
			[ 'up', '-d', 'db', 'wordpress' ],
			$dockerComposeExecutor->calls[ 0 ][ 'sub_command' ]
		);
		$this->assertSame( 'shield-local-site', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'COMPOSE_PROJECT_NAME' ] );
		$this->assertSame( '8.2', $dockerComposeExecutor->calls[ 0 ][ 'env_overrides' ][ 'PHP_VERSION' ] );

		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame( 'docker', $processRunner->calls[ 0 ][ 'command' ][ 0 ] );
		$this->assertContains( 'wp-cli', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testEnsureReadyUsesBrowserIntroFlagWhenRequested() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0 ] );
		$probe = new RecordingLocalDevSiteProbe( [ false ], [ true ], [ false ] );

		$manager = new LocalDevSiteManager(
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe
		);

		$manager->ensureReady( $this->projectRoot, true, true );

		$this->assertContains( 'SHIELD_BROWSER_TEST_INTRO=1', $processRunner->calls[ 0 ][ 'command' ] );
	}

	public function testEnsureReadyReusesHealthySiteAndOnlyRunsProvisioning() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor();
		$probe = new RecordingLocalDevSiteProbe( [ true ], [ true ], [ false ] );

		$manager = new LocalDevSiteManager(
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe
		);

		$manager->ensureReady( $this->projectRoot, false );

		$this->assertCount( 0, $dockerComposeExecutor->calls );
		$this->assertCount( 1, $processRunner->calls );
	}

	public function testEnsureReadyFailsFastOnPortConflict() :void {
		$this->expectExceptionMessage( 'Port 8888 is already in use' );

		$manager = new LocalDevSiteManager(
			new RecordingProcessRunner(),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor(),
			new RecordingLocalDevSiteProbe( [ false ], [ true ], [ true ] )
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

		$manager = new LocalDevSiteManager(
			new RecordingProcessRunner(),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor(),
			new RecordingLocalDevSiteProbe()
		);

		$manager->ensureReady( $this->projectRoot, false );
	}

	public function testResetDestroysStateAndReprovisions() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0 ] );
		$probe = new RecordingLocalDevSiteProbe( [ false ], [ true ], [ false ] );

		$manager = new LocalDevSiteManager(
			$processRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			$probe
		);

		$exitCode = $manager->reset( $this->projectRoot );

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 2, $dockerComposeExecutor->calls );
		$this->assertSame( [ 'down', '-v', '--remove-orphans' ], $dockerComposeExecutor->calls[ 0 ][ 'sub_command' ] );
		$this->assertSame( [ 'up', '-d', 'db', 'wordpress' ], $dockerComposeExecutor->calls[ 1 ][ 'sub_command' ] );
	}

	private function seedRequiredFiles( string $rootDir ) :void {
		\mkdir( Path::join( $rootDir, 'vendor' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'assets', 'dist' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'node_modules', '@playwright', 'test' ), 0777, true );
		\file_put_contents( Path::join( $rootDir, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'plugin.json' ), '{}' );
		\file_put_contents( Path::join( $rootDir, 'icwp-wpsf.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'node_modules', '@playwright', 'test', 'cli.js' ), '' );
	}
}
