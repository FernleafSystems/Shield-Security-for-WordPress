<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class RunDockerTestsScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use TempDirLifecycleTrait;
	use ScriptCommandTestTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testPhpRunnerShowsHelpWithoutRequiringDocker() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-docker-tests.php', [ '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( '--source', $output );
		$this->assertStringContainsString( '--package-targeted', $output );
		$this->assertStringContainsString( '--package-full', $output );
		$this->assertStringContainsString( '--analyze-source', $output );
		$this->assertStringContainsString( '--analyze-package', $output );
		$this->assertStringContainsString( 'Primary CLI: php bin/shield', $output );
	}

	public function testPhpRunnerRejectsUnknownArgument() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-docker-tests.php', [ '--definitely-unknown-option' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Unknown argument', $this->processOutput( $process ) );
	}

	public function testPhpRunnerRejectsMultipleModeFlags() :void {
		$this->skipIfPackageScriptUnavailable();

		$process = $this->runPhpScript( 'bin/run-docker-tests.php', [ '--source', '--package-full' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'Only one mode flag can be provided at a time',
			$this->processOutput( $process )
		);
	}

	public function testScriptShowsHelpWithoutRequiringDocker() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->requireBash();

		$process = $this->runProcess( [ 'bash', 'bin/run-docker-tests.sh', '--help' ] );
		$this->assertSame( 0, $process->getExitCode() ?? 1 );

		$output = $this->processOutput( $process );
		$this->assertStringContainsString( '--source', $output );
		$this->assertStringContainsString( '--package-targeted', $output );
		$this->assertStringContainsString( '--package-full', $output );
		$this->assertStringContainsString( '--analyze-source', $output );
		$this->assertStringContainsString( '--analyze-package', $output );
	}

	public function testScriptRejectsUnknownArgument() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->requireBash();

		$process = $this->runProcess( [ 'bash', 'bin/run-docker-tests.sh', '--definitely-unknown-option' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Unknown argument', $this->processOutput( $process ) );
	}

	public function testScriptRejectsMultipleModeFlags() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->requireBash();

		$process = $this->runProcess( [ 'bash', 'bin/run-docker-tests.sh', '--source', '--package-full' ] );
		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString(
			'Only one mode flag can be provided at a time',
			$this->processOutput( $process )
		);
	}

	public function testSourceModeRunsSetupOnceAndSetsInnerSkipFlag() :void {
		$this->skipIfPackageScriptUnavailable();

		$shimDir = $this->createTrackedTempDir( 'shield-docker-shims-' );
		$capturePath = Path::join( $shimDir, 'captured-docker-commands.txt' );
		$this->writeDockerShim( $shimDir );
		$this->writeBashVersionShim( $shimDir );

		$path = \getenv( 'PATH' );
		$env = [
			'PATH' => $shimDir.\PATH_SEPARATOR.( \is_string( $path ) ? $path : '' ),
			'SHIELD_TEST_DOCKER_CAPTURE' => $capturePath,
		];
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$pathExt = \getenv( 'PATHEXT' );
			$env[ 'PATHEXT' ] = '.COM;.EXE;.BAT;.CMD'.( \is_string( $pathExt ) ? ';'.$pathExt : '' );
			$env[ 'SHIELD_BASH_BINARY' ] = Path::join( $shimDir, 'bash.cmd' );
		}

		$process = $this->runPhpScript( 'bin/run-docker-tests.php', [ '--source' ], $env );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertFileExists( $capturePath, 'Docker shim did not capture any commands.' );

		$capturedLines = \file( $capturePath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );
		$this->assertIsArray( $capturedLines );
		$this->assertNotEmpty( $capturedLines );

		$composerInstallLines = \array_values( \array_filter(
			$capturedLines,
			static function ( string $line ) :bool {
				return \str_contains( $line, 'composer install --no-interaction --no-cache' );
			}
		) );
		$this->assertCount( 1, $composerInstallLines );

		$buildConfigLines = \array_values( \array_filter(
			$capturedLines,
			static function ( string $line ) :bool {
				return \str_contains( $line, 'composer build:config' );
			}
		) );
		$this->assertCount( 1, $buildConfigLines );

		$assetBuildLines = \array_values( \array_filter(
			$capturedLines,
			static function ( string $line ) :bool {
				return \str_contains( $line, 'node:' ) && \str_contains( $line, 'npm run build' );
			}
		) );
		$this->assertCount( 1, $assetBuildLines );

		$latestRunnerLines = \array_values( \array_filter(
			$capturedLines,
			static function ( string $line ) :bool {
				return \str_contains( $line, 'run --rm' )
					&& \str_contains( $line, 'SHIELD_SKIP_INNER_SETUP=1' )
					&& \str_contains( $line, 'test-runner-latest' );
			}
		) );
		$this->assertCount( 1, $latestRunnerLines );

		$previousRunnerLines = \array_values( \array_filter(
			$capturedLines,
			static function ( string $line ) :bool {
				return \str_contains( $line, 'run --rm' )
					&& \str_contains( $line, 'SHIELD_SKIP_INNER_SETUP=1' )
					&& \str_contains( $line, 'test-runner-previous' );
			}
		) );
		$this->assertCount( 1, $previousRunnerLines );
	}

	public function testSourceModeDoesNotLeakInheritedPackagePathToDockerProcesses() :void {
		$this->skipIfPackageScriptUnavailable();

		$shimDir = $this->createTrackedTempDir( 'shield-docker-env-shims-' );
		$capturePath = Path::join( $shimDir, 'captured-docker-env.txt' );
		$this->writeDockerEnvCaptureShim( $shimDir );
		$this->writeBashVersionShim( $shimDir );

		$path = \getenv( 'PATH' );
		$env = [
			'PATH' => $shimDir.\PATH_SEPARATOR.( \is_string( $path ) ? $path : '' ),
			'SHIELD_TEST_DOCKER_CAPTURE' => $capturePath,
			'SHIELD_PACKAGE_PATH' => '/tmp/host-package-path',
		];
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$pathExt = \getenv( 'PATHEXT' );
			$env[ 'PATHEXT' ] = '.COM;.EXE;.BAT;.CMD'.( \is_string( $pathExt ) ? ';'.$pathExt : '' );
			$env[ 'SHIELD_BASH_BINARY' ] = Path::join( $shimDir, 'bash.cmd' );
		}

		$process = $this->runPhpScript( 'bin/run-docker-tests.php', [ '--source' ], $env );
		$this->assertSame( 0, $process->getExitCode() ?? 1, $this->processOutput( $process ) );
		$this->assertFileExists( $capturePath, 'Docker shim did not capture any commands.' );

		$capturedLines = \file( $capturePath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );
		$this->assertIsArray( $capturedLines );
		$this->assertNotEmpty( $capturedLines );

		foreach ( $capturedLines as $line ) {
			$this->assertStringContainsString( 'ENV=__UNSET__', $line );
		}
	}

	private function requireBash() :void {
		$process = new Process( [ 'bash', '--version' ], $this->getPluginRoot() );
		$process->run();
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$this->markTestSkipped( 'bash is required for run-docker-tests.sh behavior tests.' );
		}
	}

	private function writeDockerShim( string $shimDir ) :void {
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$shimContent = <<<'CMD'
@echo off
setlocal
if "%SHIELD_TEST_DOCKER_CAPTURE%"=="" exit /b 2
>> "%SHIELD_TEST_DOCKER_CAPTURE%" echo %*
exit /b 0
CMD;
			\file_put_contents( Path::join( $shimDir, 'docker.cmd' ), $shimContent );
			return;
		}

		$shimPath = Path::join( $shimDir, 'docker' );
		$shimContent = <<<'SH'
#!/usr/bin/env sh
set -eu
: "${SHIELD_TEST_DOCKER_CAPTURE:?}"
printf '%s\n' "$*" >> "$SHIELD_TEST_DOCKER_CAPTURE"
exit 0
SH;
		\file_put_contents( $shimPath, $shimContent );
		\chmod( $shimPath, 0755 );
	}

	private function writeDockerEnvCaptureShim( string $shimDir ) :void {
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$shimContent = <<<'CMD'
@echo off
setlocal
if "%SHIELD_TEST_DOCKER_CAPTURE%"=="" exit /b 2
set "_env=__UNSET__"
if defined SHIELD_PACKAGE_PATH set "_env=%SHIELD_PACKAGE_PATH%"
>> "%SHIELD_TEST_DOCKER_CAPTURE%" echo ENV=%_env% ARGS=%*
exit /b 0
CMD;
			\file_put_contents( Path::join( $shimDir, 'docker.cmd' ), $shimContent );
			return;
		}

		$shimPath = Path::join( $shimDir, 'docker' );
		$shimContent = <<<'SH'
#!/usr/bin/env sh
set -eu
: "${SHIELD_TEST_DOCKER_CAPTURE:?}"
env_value="__UNSET__"
if [ "${SHIELD_PACKAGE_PATH+x}" = "x" ]; then
	env_value="$SHIELD_PACKAGE_PATH"
fi
printf 'ENV=%s ARGS=%s\n' "$env_value" "$*" >> "$SHIELD_TEST_DOCKER_CAPTURE"
exit 0
SH;
		\file_put_contents( $shimPath, $shimContent );
		\chmod( $shimPath, 0755 );
	}

	private function writeBashVersionShim( string $shimDir ) :void {
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			$shimContent = <<<'CMD'
@echo off
echo LATEST_VERSION=6.9
echo PREVIOUS_VERSION=6.8.3
exit /b 0
CMD;
			\file_put_contents( Path::join( $shimDir, 'bash.cmd' ), $shimContent );
			return;
		}

		$shimPath = Path::join( $shimDir, 'bash' );
		$shimContent = <<<'SH'
#!/usr/bin/env sh
set -eu
echo "LATEST_VERSION=6.9"
echo "PREVIOUS_VERSION=6.8.3"
exit 0
SH;
		\file_put_contents( $shimPath, $shimContent );
		\chmod( $shimPath, 0755 );
	}
}
