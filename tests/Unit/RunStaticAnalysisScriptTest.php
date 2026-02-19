<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Symfony\Component\Process\Process;

class RunStaticAnalysisScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

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
}
