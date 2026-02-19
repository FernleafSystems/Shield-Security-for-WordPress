<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
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
}
