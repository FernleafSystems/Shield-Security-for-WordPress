<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

/**
 * Safety checks for local Playground helper script and composer wiring.
 */
class RunPlaygroundLocalScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testRunPlaygroundScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/run-playground-local.php' );
	}

	public function testRunPlaygroundScriptHelpShowsExpectedCliSurface() :void {
		$this->skipIfPackageScriptUnavailable();
		$process = $this->runPhpScript( 'bin/run-playground-local.php', [ '--help' ] );

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( '--run-blueprint', $output );
		$this->assertStringContainsString( '--clean', $output );
		$this->assertStringContainsString( '--retention-days', $output );
		$this->assertStringContainsString( '--max-runs', $output );
		$this->assertStringContainsString( '--runtime-root', $output );
		$this->assertStringContainsString( '--plugin-root', $output );
		$this->assertStringContainsString( '--strict', $output );
	}

	public function testComposerDeclaresPlaygroundCleanScript() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packaged artifacts (source-only assertion)' );
		}

		$cleanCommands = $this->getComposerScriptCommands( 'playground:local:clean' );
		$this->assertContains( '@php bin/run-playground-local.php --clean', $cleanCommands );

		$packageCheckCommands = $this->getComposerScriptCommands( 'playground:package:check' );
		$this->assertContains(
			'@php bin/run-playground-local.php --run-blueprint --plugin-root=./shield-package',
			$packageCheckCommands
		);
	}

	public function testRunPlaygroundCheckFailsFastForMissingPluginRoot() :void {
		$this->skipIfPackageScriptUnavailable();
		$missingPluginRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shield-playground-missing-'.bin2hex( random_bytes( 4 ) );
		$this->assertDirectoryDoesNotExist( $missingPluginRoot );

		$output = [];
		$returnCode = 0;
		\exec(
			'php '.\escapeshellarg( $this->getPluginFilePath( 'bin/run-playground-local.php' ) )
			.' --run-blueprint --plugin-root='.escapeshellarg( $missingPluginRoot ).' 2>&1',
			$output,
			$returnCode
		);

		$this->assertSame( 2, $returnCode, 'Missing plugin root should fail with environment exit code (2).' );
		$this->assertStringContainsString( 'Plugin root directory not found:', \implode( "\n", $output ) );
	}
}
