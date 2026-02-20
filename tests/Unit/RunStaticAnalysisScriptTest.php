<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class RunStaticAnalysisScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testRunStaticAnalysisScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/run-static-analysis.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame(
			0,
			$returnCode,
			'bin/run-static-analysis.php should have valid PHP syntax: '.\implode( "\n", $output )
		);
	}

	public function testRunStaticAnalysisHelpReturnsUsage() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, $this->getPluginFilePath( 'bin/run-static-analysis.php' ), '--help' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Usage: php bin/run-static-analysis.php', $process->getOutput() );
	}

	public function testRunStaticAnalysisPackageModeDelegatesWithNormalizedScriptPath() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$shimDir = $this->createTrackedTempDir( 'shield-fake-bash-' );
		$capturePath = Path::join( $shimDir, 'captured-bash-args.txt' );
		$this->writeBashShim( $shimDir );

		$path = \getenv( 'PATH' );
		$env = [
			'PATH' => $shimDir.\PATH_SEPARATOR.( \is_string( $path ) ? $path : '' ),
			'SHIELD_TEST_BASH_CAPTURE' => $capturePath,
		];
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$pathExt = \getenv( 'PATHEXT' );
			$env[ 'PATHEXT' ] = '.COM;.EXE;.BAT;.CMD'.( \is_string( $pathExt ) ? ';'.$pathExt : '' );
		}

		$process = new Process(
			[ \PHP_BINARY, $this->getPluginFilePath( 'bin/run-static-analysis.php' ), '--package' ],
			$this->getPluginRoot(),
			$env
		);
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1, $process->getErrorOutput() );
		$this->assertFileExists( $capturePath, 'Fake bash shim did not capture any arguments.' );

		$capturedLines = \file( $capturePath, \FILE_IGNORE_NEW_LINES );
		$this->assertIsArray( $capturedLines );
		$this->assertNotEmpty( $capturedLines );
		$capturedScriptPath = (string)( $capturedLines[ 0 ] ?? '' );
		$capturedModeFlag = (string)( $capturedLines[ 1 ] ?? '' );

		$this->assertNotSame( '', $capturedScriptPath, 'Expected run-static-analysis to pass docker script path to bash.' );
		$this->assertSame( '--analyze-package', $capturedModeFlag );
		$this->assertStringNotContainsString( '\\', $capturedScriptPath );
		$this->assertStringEndsWith(
			'/bin/run-docker-tests.sh',
			\str_replace( '\\', '/', $capturedScriptPath )
		);
	}

	private function writeBashShim( string $shimDir ) :void {
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$shimContent = <<<'CMD'
@echo off
setlocal
if "%SHIELD_TEST_BASH_CAPTURE%"=="" exit /b 2
> "%SHIELD_TEST_BASH_CAPTURE%" (
	echo %~1
	echo %~2
)
exit /b 0
CMD;
			\file_put_contents( Path::join( $shimDir, 'bash.cmd' ), $shimContent );
			return;
		}

		$shimPath = Path::join( $shimDir, 'bash' );
		$shimContent = <<<'SH'
#!/usr/bin/env sh
set -eu
: "${SHIELD_TEST_BASH_CAPTURE:?}"
{
	printf '%s\n' "${1:-}"
	printf '%s\n' "${2:-}"
} > "$SHIELD_TEST_BASH_CAPTURE"
exit 0
SH;
		\file_put_contents( $shimPath, $shimContent );
		\chmod( $shimPath, 0755 );
	}
}
