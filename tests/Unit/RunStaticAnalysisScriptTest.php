<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

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

	public function testRunStaticAnalysisUsesSharedProcessRunner() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/run-static-analysis.php', 'static analysis runner script' );
		$this->assertStringContainsString( 'Tooling\\Process\\ProcessRunner', $content );
		$this->assertStringNotContainsString( 'use Symfony\\Component\\Process\\Process;', $content );
	}
}

