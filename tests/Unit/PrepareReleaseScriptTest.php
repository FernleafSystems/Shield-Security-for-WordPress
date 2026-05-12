<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class PrepareReleaseScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testPrepareReleaseScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/prepare-release.php' );
	}

	public function testPrepareReleaseHelpReturnsUsage() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/prepare-release.php', [ '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'Usage: php bin/prepare-release.php', $output );
		$this->assertStringContainsString( '--version', $output );
		$this->assertStringContainsString( '--build', $output );
	}

	public function testPrepareReleaseWithoutOptionsFails() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/prepare-release.php' );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'No options provided', $this->processOutput( $process ) );
	}
}
