<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\BrowserTestLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalDevSiteManager;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingDockerComposeExecutor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingLocalDevSiteProbe;
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
		$siteProcessRunner = new RecordingProcessRunner( [ 0 ] );
		$siteManager = new LocalDevSiteManager(
			$siteProcessRunner,
			new RecordingTestingEnvironmentResolver(),
			new RecordingDockerComposeExecutor( [ 0 ] ),
			new RecordingLocalDevSiteProbe( [ false ], [ true ], [ false ] )
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
			LocalDevSiteManager::SITE_URL,
			$playwrightRunner->calls[ 0 ][ 'env_overrides' ][ 'SHIELD_BROWSER_BASE_URL' ]
		);
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
