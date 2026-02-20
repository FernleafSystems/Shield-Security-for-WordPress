<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

class RunDockerTestsScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testPackagedPhpStanDelegatesToDedicatedPhpRunnerScript() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/run-docker-tests.sh', 'docker tests runner script' );
		$this->assertStringContainsString( 'bin/run-packaged-phpstan.php', $content );
	}

	public function testPackagedPhpStanRunnerUsesWindowsSafePhpPaths() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/run-docker-tests.sh', 'docker tests runner script' );

		$this->assertStringContainsString( 'php "./bin/run-packaged-phpstan.php"', $content );
		$this->assertStringContainsString( 'if command -v cygpath >/dev/null 2>&1; then', $content );
		$this->assertStringContainsString( 'project_root_for_php="$(cygpath -m "$PROJECT_ROOT")"', $content );
		$this->assertStringContainsString( 'package_dir_for_php="$(cygpath -m "$PACKAGE_DIR")"', $content );
		$this->assertStringContainsString( '--project-root="$project_root_for_php"', $content );
		$this->assertStringContainsString( '--package-dir="$package_dir_for_php"', $content );
		$this->assertStringNotContainsString( 'php "$PROJECT_ROOT/bin/run-packaged-phpstan.php"', $content );
	}

	public function testScriptShowsHelpWithoutRequiringDocker() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}
		$this->requireBash();

		$process = new Process(
			[ 'bash', $this->getPluginFilePath( 'bin/run-docker-tests.sh' ), '--help' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Usage:', $process->getOutput().$process->getErrorOutput() );
	}

	public function testScriptRejectsUnknownArgument() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}
		$this->requireBash();

		$process = new Process(
			[ 'bash', $this->getPluginFilePath( 'bin/run-docker-tests.sh' ), '--definitely-unknown-option' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Unknown argument', $process->getOutput().$process->getErrorOutput() );
	}

	private function requireBash() :void {
		$process = new Process( [ 'bash', '--version' ], $this->getPluginRoot() );
		$process->run();
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$this->markTestSkipped( 'bash is required for run-docker-tests.sh behavior tests.' );
		}
	}
}
