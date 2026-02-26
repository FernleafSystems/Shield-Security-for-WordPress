<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class RunPackagedPhpStanScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use TempDirLifecycleTrait;
	use ScriptCommandTestTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunPackagedPhpStanScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-packaged-phpstan.php' );
	}

	public function testRunPackagedPhpStanScriptShowsUsageWhenArgsMissing() :void {
		$this->skipIfPackageScriptUnavailable();
		$process = $this->runPhpScript( 'bin/run-packaged-phpstan.php' );

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Usage: php bin/run-packaged-phpstan.php', $process->getErrorOutput() );
	}

	public function testRunPackagedPhpStanScriptSurfacesPreflightFailures() :void {
		$this->skipIfPackageScriptUnavailable();

		$missingVendorPackageDir = $this->createTrackedTempDir( 'shield-missing-vendor-' );
		$process = $this->runPhpScript(
			'bin/run-packaged-phpstan.php',
			[
				'--project-root='.$this->getPluginRoot(),
				'--composer-image=composer:2',
				'--package-dir='.$missingVendorPackageDir,
				'--package-dir-relative=tmp/shield-missing-vendor',
			]
		);

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'ERROR: Packaged vendor autoload not found:',
			$process->getErrorOutput()
		);
	}

	public function testRunPackagedPhpStanScriptHelpReturnsZero() :void {
		$this->skipIfPackageScriptUnavailable();
		$process = $this->runPhpScript( 'bin/run-packaged-phpstan.php', [ '--help' ] );

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Usage: php bin/run-packaged-phpstan.php', $process->getOutput() );
	}
}
