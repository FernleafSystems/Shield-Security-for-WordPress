<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalIntegrationTestLane;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

class PowerTestToolingContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testComposerScriptsExposeOnlyTheSupportedTestingSurface() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$unitCommands = $this->getComposerScriptCommands( 'test:unit' );
		$this->assertContains( '@build:config', $unitCommands );
		$this->assertContains( '@php bin/run-unit-tests.php --runner-mode=auto', $unitCommands );

		$integrationCommands = $this->getComposerScriptCommands( 'test:integration' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $integrationCommands );
		$this->assertContains( '@build:config', $integrationCommands );
		$this->assertContains( '@php bin/shield test:integration-local', $integrationCommands );

		$browserCommands = $this->getComposerScriptCommands( 'test:browser' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $browserCommands );
		$this->assertContains( '@php bin/shield test:browser', $browserCommands );

		$packageCommands = $this->getComposerScriptCommands( 'test:package' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $packageCommands );
		$this->assertContains( '@php bin/shield test:package-targeted', $packageCommands );

		$analyzeCommands = $this->getComposerScriptCommands( 'analyze' );
		$this->assertSame( [ '@php bin/shield analyze:source' ], $analyzeCommands );

		$composer = $this->decodePluginJsonFile( 'composer.json', 'composer.json' );
		$scripts = $composer[ 'scripts' ] ?? [];
		$this->assertIsArray( $scripts );

		foreach ( [
			'test:fast',
			'test:unit:serial',
			'test:unit:parallel',
			'test:unit:verify-power',
			'test:passkeys',
			'test:source',
			'test:integration:local',
			'test:package-targeted',
			'test:package-full',
			'analyze:source',
			'analyze:package',
			'playground:local',
			'playground:local:check',
			'playground:package:check',
			'playground:local:clean',
		] as $removedScript ) {
			$this->assertArrayNotHasKey( $removedScript, $scripts );
		}
	}

	public function testLocalIntegrationLaneRemainsSerialPhpUnit() :void {
		$lane = new LocalIntegrationTestLane();
		$reflection = new \ReflectionClass( $lane );
		$method = $reflection->getMethod( 'buildPhpUnitCommand' );
		$method->setAccessible( true );

		$command = $method->invoke( $lane, [ '--filter', 'InfrastructureSmokeTest' ] );
		$this->assertIsArray( $command );
		$this->assertSame( \PHP_BINARY, $command[ 0 ] ?? null );
		$this->assertContains( './vendor/phpunit/phpunit/phpunit', $command );
		$this->assertNotContains( 'paratest', $command );
		$this->assertContains( 'phpunit-integration.xml', $command );
	}

	public function testIntegrationBootstrapFailsFastWhenParallelTokensArePresent() :void {
		$bootstrapPath = \dirname( __DIR__, 2 ).'/tests/Integration/bootstrap.php';
		$process = new Process( [
			\PHP_BINARY,
			'-r',
			"putenv('TEST_TOKEN=1'); require '".\str_replace( '\\', '\\\\', $bootstrapPath )."';",
		], \dirname( __DIR__, 2 ) );

		$process->run();
		$this->assertSame( 1, $process->getExitCode() );
		$this->assertStringContainsString( 'integration tests are serial-only', \strtolower( $process->getErrorOutput().$process->getOutput() ) );
	}
}
