<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\FileSystemUtils;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class RunPackagedPhpStanScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testRunPackagedPhpStanScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/run-packaged-phpstan.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame(
			0,
			$returnCode,
			'bin/run-packaged-phpstan.php should have valid PHP syntax: '.\implode( "\n", $output )
		);
	}

	public function testRunPackagedPhpStanScriptShowsUsageWhenArgsMissing() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, $this->getPluginFilePath( 'bin/run-packaged-phpstan.php' ) ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 1, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Usage: php bin/run-packaged-phpstan.php', $process->getErrorOutput() );
	}

	public function testRunPackagedPhpStanScriptSurfacesPreflightFailures() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$missingVendorPackageDir = $this->createTempDir( 'shield-missing-vendor-' );
		try {
			$process = new Process(
				[
					\PHP_BINARY,
					$this->getPluginFilePath( 'bin/run-packaged-phpstan.php' ),
					'--project-root='.$this->getPluginRoot(),
					'--composer-image=composer:2',
					'--package-dir='.$missingVendorPackageDir,
					'--package-dir-relative=tmp/shield-missing-vendor',
				],
				$this->getPluginRoot()
			);
			$process->run();

			$this->assertSame( 1, $process->getExitCode() ?? 1 );
			$this->assertStringContainsString(
				'ERROR: Packaged vendor autoload not found:',
				$process->getErrorOutput()
			);
		}
		finally {
			FileSystemUtils::removeDirectoryRecursive( $missingVendorPackageDir );
		}
	}

	public function testRunPackagedPhpStanScriptHelpReturnsZero() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$process = new Process(
			[ \PHP_BINARY, $this->getPluginFilePath( 'bin/run-packaged-phpstan.php' ), '--help' ],
			$this->getPluginRoot()
		);
		$process->run();

		$this->assertSame( 0, $process->getExitCode() ?? 1 );
		$this->assertStringContainsString( 'Usage: php bin/run-packaged-phpstan.php', $process->getOutput() );
	}

	private function createTempDir( string $prefix ) :string {
		$path = Path::join( \sys_get_temp_dir(), $prefix.\bin2hex( \random_bytes( 6 ) ) );
		\mkdir( $path, 0777, true );
		return $path;
	}
}
