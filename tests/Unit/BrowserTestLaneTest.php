<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
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

class BrowserTestLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	public function testRunEnsuresLocalSiteThenInvokesPlaywrightWithBaseUrl() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-' );
		$this->seedRequiredFiles( $projectRoot );

		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteProcessRunner = new RecordingProcessRunner( [ 0, 0, 0, 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0, 0 ] );
		$siteManager = new LocalSiteManager(
			LocalSiteDefinitions::browserLane( 1 ),
			$siteProcessRunner,
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] ),
			new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] )
		);

		$lane = new BrowserTestLane( $playwrightRunner, $siteManager );
		\ob_start();
		try {
			$exitCode = $lane->run( $projectRoot, [ '--workers=1' ] );
		}
		finally {
			\ob_end_clean();
		}

		$this->assertSame( 0, $exitCode );
		$this->assertCount( 1, $playwrightRunner->calls );
		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/run-node-tool.php',
				'playwright',
				'test',
				'--workers=1',
			],
			$playwrightRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame(
			LocalSiteDefinitions::browserLane( 1 )->siteUrl(),
			$playwrightRunner->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_BROWSER_BASE_URL' ]
		);
		$this->assertSame( '1', $playwrightRunner->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_BROWSER_LANE_INDEX' ] );
		$this->assertSame( './test-results/playwright/lane-1', $playwrightRunner->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_BROWSER_OUTPUT_DIR' ] );
		$this->assertCount( 3, $dockerComposeExecutor->calls );
		$this->assertSame( [ 'up', '-d', 'db' ], $dockerComposeExecutor->calls[ 0 ][ 'sub_command' ] );
		$this->assertSame( [ 'down', '-v', '--remove-orphans' ], $dockerComposeExecutor->calls[ 1 ][ 'sub_command' ] );
		$this->assertSame( [ 'up', '-d', 'wordpress' ], $dockerComposeExecutor->calls[ 2 ][ 'sub_command' ] );
	}

	public function testRunAddsSingleWorkerDefaultWhenCallerDoesNotSpecifyWorkers() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-default-workers-' );
		$this->seedRequiredFiles( $projectRoot );

		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = new LocalSiteManager(
			LocalSiteDefinitions::browserLane( 1 ),
			new RecordingProcessRunner( [ 0, 0, 0, 0 ] ),
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor( [ 0, 0, 0 ] ),
			new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] ),
			new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] )
		);

		$lane = new BrowserTestLane( $playwrightRunner, $siteManager );
		\ob_start();
		try {
			$exitCode = $lane->run( $projectRoot, [ 'tests/browser/action-router/drill-down-flows.spec.js' ] );
		}
		finally {
			\ob_end_clean();
		}

		$this->assertSame( 0, $exitCode );
		$this->assertSame( '--workers=1', $playwrightRunner->calls[ 0 ][ 'command' ][ 4 ] );
	}

	public function testRunFailsBeforeResetWhenPlaywrightIsMissing() :void {
		$projectRoot = $this->createTrackedTempDir( 'shield-browser-lane-missing-playwright-' );
		\mkdir( Path::join( $projectRoot, 'vendor' ), 0777, true );
		\mkdir( Path::join( $projectRoot, 'assets', 'dist' ), 0777, true );
		\file_put_contents( Path::join( $projectRoot, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents( Path::join( $projectRoot, 'plugin.json' ), '{}' );
		\file_put_contents( Path::join( $projectRoot, 'icwp-wpsf.php' ), '<?php' );

		$playwrightRunner = new RecordingProcessRunner( [ 0 ] );
		$dockerComposeExecutor = new RecordingDockerComposeExecutor( [ 0, 0 ] );
		$siteManager = new LocalSiteManager(
			LocalSiteDefinitions::test(),
			new RecordingProcessRunner( [ 0 ] ),
			new RecordingTestingEnvironmentResolver(),
			$dockerComposeExecutor,
			new RecordingLocalSiteProbe( [ true ], [ true, true ], [ false ] ),
			new RecordingLocalSiteRuntimeRefresher( [ '', 'wordpress-container' ] )
		);
		$lane = new BrowserTestLane( $playwrightRunner, $siteManager );

		\ob_start();
		try {
			$exitCode = $lane->run( $projectRoot );
		}
		finally {
			\ob_end_clean();
			$this->assertCount( 0, $dockerComposeExecutor->calls );
			$this->assertCount( 0, $playwrightRunner->calls );
		}

		$this->assertSame( 1, $exitCode );
	}

	private function seedRequiredFiles( string $rootDir ) :void {
		\mkdir( Path::join( $rootDir, 'vendor' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'assets', 'dist' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'node_modules', '@playwright', 'test' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'plugin-spec' ), 0777, true );
		\mkdir( Path::join( $rootDir, 'bin' ), 0777, true );
		\file_put_contents( Path::join( $rootDir, 'vendor', 'autoload.php' ), '<?php' );
		\file_put_contents(
			Path::join( $rootDir, 'plugin-spec', '01_properties.json' ),
			\json_encode( [
				'version' => '21.99.3',
				'build' => '202604.1701',
			], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '{}'
		);
		\file_put_contents(
			Path::join( $rootDir, 'plugin.json' ),
			\json_encode( [
				'properties' => [
					'version' => '21.99.3',
					'build' => '202604.1701',
				],
			], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '{}'
		);
		\file_put_contents( Path::join( $rootDir, 'bin', 'build-config.php' ), '<?php' );
		\file_put_contents( Path::join( $rootDir, 'icwp-wpsf.php' ), "<?php\n/*\n * Version: 21.99.3\n */\n" );
		\file_put_contents( Path::join( $rootDir, 'node_modules', '@playwright', 'test', 'cli.js' ), '' );
	}
}
