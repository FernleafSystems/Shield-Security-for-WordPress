<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

class RunDockerTestsScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testPhpRunnerShowsHelpWithoutRequiringDocker() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, 'bin/run-docker-tests.php', '--help' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( '--source', $output );
		$this->assertStringContainsString( '--package-targeted', $output );
		$this->assertStringContainsString( '--package-full', $output );
		$this->assertStringContainsString( '--analyze-source', $output );
		$this->assertStringContainsString( '--analyze-package', $output );
	}

	public function testPhpRunnerRejectsUnknownArgument() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, 'bin/run-docker-tests.php', '--definitely-unknown-option' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Unknown argument', $process->getOutput().$process->getErrorOutput() );
	}

	public function testPhpRunnerRejectsMultipleModeFlags() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, 'bin/run-docker-tests.php', '--source', '--package-full' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'Only one mode flag can be provided at a time',
			$process->getOutput().$process->getErrorOutput()
		);
	}

	public function testScriptShowsHelpWithoutRequiringDocker() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}
		$this->requireBash();

		$process = new Process(
			[ 'bash', 'bin/run-docker-tests.sh', '--help' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$output = $process->getOutput().$process->getErrorOutput();
		$this->assertStringContainsString( '--source', $output );
		$this->assertStringContainsString( '--package-targeted', $output );
		$this->assertStringContainsString( '--package-full', $output );
		$this->assertStringContainsString( '--analyze-source', $output );
		$this->assertStringContainsString( '--analyze-package', $output );
	}

	public function testScriptRejectsUnknownArgument() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}
		$this->requireBash();

		$process = new Process(
			[ 'bash', 'bin/run-docker-tests.sh', '--definitely-unknown-option' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Unknown argument', $process->getOutput().$process->getErrorOutput() );
	}

	public function testScriptRejectsMultipleModeFlags() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}
		$this->requireBash();

		$process = new Process(
			[ 'bash', 'bin/run-docker-tests.sh', '--source', '--package-full' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'Only one mode flag can be provided at a time',
			$process->getOutput().$process->getErrorOutput()
		);
	}

	private function requireBash() :void {
		$process = new Process( [ 'bash', '--version' ], $this->getPluginRoot() );
		$process->run();
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$this->markTestSkipped( 'bash is required for run-docker-tests.sh behavior tests.' );
		}
	}
}
