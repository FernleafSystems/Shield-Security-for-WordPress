<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

/**
 * Safety checks for local Playground helper script and composer wiring.
 */
class RunPlaygroundLocalScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testRunPlaygroundScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/run-playground-local.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame(
			0,
			$returnCode,
			'bin/run-playground-local.php should have valid PHP syntax: '.\implode( "\n", $output )
		);
	}

	public function testRunPlaygroundScriptHelpShowsExpectedCliSurface() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, $this->getPluginFilePath( 'bin/run-playground-local.php' ), '--help' ],
			$this->getPluginRoot()
		);
		$process->run();

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
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/run-playground-local.php' );
		$missingPluginRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shield-playground-missing-'.bin2hex( random_bytes( 4 ) );
		$this->assertDirectoryDoesNotExist( $missingPluginRoot );

		$output = [];
		$returnCode = 0;
		\exec(
			'php '.\escapeshellarg( $scriptPath ).' --run-blueprint --plugin-root='.escapeshellarg( $missingPluginRoot ).' 2>&1',
			$output,
			$returnCode
		);

		$this->assertSame( 2, $returnCode, 'Missing plugin root should fail with environment exit code (2).' );
		$this->assertStringContainsString( 'Plugin root directory not found:', \implode( "\n", $output ) );
	}
}
