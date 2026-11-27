<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class BuildConfigScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testBuildConfigScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/build-config.php' );
	}

	public function testComposerBuildConfigScriptExists() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'Source-only: checks the repository\'s Composer scripts, not the built package.' );
		}

		$commands = $this->getComposerScriptCommands( 'build:config' );
		$this->assertContains( '@php bin/build-config.php', $commands );
	}

	public function testBuildConfigScriptRunsSuccessfully() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/build-config.php' );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertStringContainsString( 'plugin.json', $this->processOutput( $process ) );
	}
}
