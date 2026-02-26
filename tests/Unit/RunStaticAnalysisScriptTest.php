<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class RunStaticAnalysisScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testRunStaticAnalysisScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-static-analysis.php' );
	}

	public function testRunStaticAnalysisHelpReturnsUsage() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-static-analysis.php', [ '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( 'Usage: php bin/run-static-analysis.php', $output );
		$this->assertStringContainsString( 'Primary CLI: php bin/shield', $output );
	}

	public function testRunStaticAnalysisRejectsUnknownArgument() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-static-analysis.php', [ '--definitely-unknown-option' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Unknown argument', $this->processOutput( $process ) );
	}

	public function testRunStaticAnalysisRejectsMultipleModeFlags() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-static-analysis.php', [ '--source', '--package' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'Only one mode flag can be provided at a time',
			$this->processOutput( $process )
		);
	}

	public function testComposerAnalyzeDefaultsToAnalyzeSource() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'analyze' );
		$this->assertContains( '@analyze:source', $commands );
	}

	public function testComposerAnalyzeSourceUsesShieldCli() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'analyze:source' );
		$this->assertContains( '@php bin/shield analyze:source', $commands );
	}

	public function testComposerAnalyzePackageUsesShieldCli() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'analyze:package' );
		$this->assertContains( '@php bin/shield analyze:package', $commands );
	}
}
