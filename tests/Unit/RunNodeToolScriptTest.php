<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class RunNodeToolScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testRunNodeToolScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-node-tool.php' );
	}

	public function testRunNodeToolScriptHelpShowsExpectedCliSurface() :void {
		$this->skipIfPackageScriptUnavailable();
		$process = $this->runPhpScript( 'bin/run-node-tool.php', [ '--help' ] );

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( 'playwright', $output );
		$this->assertStringContainsString( 'webpack', $output );
		$this->assertStringContainsString( 'SHIELD_NODE_BINARY', $output );
	}

	public function testRunNodeToolScriptUnsupportedToolShowsSupportedTools() :void {
		$this->skipIfPackageScriptUnavailable();
		$process = $this->runPhpScript( 'bin/run-node-tool.php', [ 'bogus-tool' ] );

		$this->assertSame( 2, $process->getExitCode() ?? 0 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( 'Unsupported tool', $output );
		$this->assertStringContainsString( 'playwright, webpack', $output );
	}
}
