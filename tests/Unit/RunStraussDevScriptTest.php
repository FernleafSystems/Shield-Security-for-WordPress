<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class RunStraussDevScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testRunStraussDevScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-strauss-dev.php' );
	}

	public function testRunStraussDevHelpReturnsUsage() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-strauss-dev.php', [ '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'Development Strauss Runner', $output );
		$this->assertStringContainsString( '--clean', $output );
		$this->assertStringContainsString( '--strauss-version', $output );
		$this->assertStringContainsString( '--strauss-fork-branch', $output );
	}

	public function testComposerStraussScriptsRemainMapped() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'Source-only: checks the repository\'s Composer scripts, not the built package.' );
		}

		$devCommands = $this->getComposerScriptCommands( 'strauss:dev' );
		$this->assertContains( '@php bin/run-strauss-dev.php', $devCommands );

		$cleanCommands = $this->getComposerScriptCommands( 'strauss:clean' );
		$this->assertContains( '@php bin/run-strauss-dev.php --clean', $cleanCommands );
	}
}
