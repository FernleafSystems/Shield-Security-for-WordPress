<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ScriptCommandTestTrait;

class ClassifyPackagedPhpStanScriptTest extends BaseUnitTest {

	use PluginPathsTrait;
	use ScriptCommandTestTrait;

	public function testClassifierScriptHasValidSyntax() :void {
		$this->skipIfPackageScriptUnavailable();
		$this->assertPhpScriptSyntaxValid( 'bin/classify-packaged-phpstan.php' );
	}

	public function testFindingsOnlyResultReturnsSuccess() :void {
		$result = $this->runClassifierScript(
			"noise\n{\"totals\":{\"errors\":0,\"file_errors\":2}}\n",
			1
		);

		$this->assertSame( 0, $result[ 'exit_code' ] );
		$this->assertStringContainsString(
			'Packaged PHPStan completed with findings (informational only).',
			$result[ 'output' ]
		);
	}

	public function testCleanResultReturnsSuccess() :void {
		$result = $this->runClassifierScript(
			'irrelevant when exit code is zero',
			0
		);

		$this->assertSame( 0, $result[ 'exit_code' ] );
		$this->assertStringContainsString(
			'Packaged PHPStan analysis completed with no findings.',
			$result[ 'output' ]
		);
	}

	public function testInfrastructureFailureReturnsNonZero() :void {
		$result = $this->runClassifierScript(
			'{"totals":{"errors":1,"file_errors":0}}',
			1
		);

		$this->assertSame( 1, $result[ 'exit_code' ] );
		$this->assertStringContainsString(
			'Packaged PHPStan returned non-zero without reportable findings.',
			$result[ 'output' ]
		);
	}

	public function testParseFailureReturnsNonZero() :void {
		$result = $this->runClassifierScript(
			'not json',
			1
		);

		$this->assertSame( 1, $result[ 'exit_code' ] );
		$this->assertStringContainsString(
			'Packaged PHPStan output could not be parsed as JSON',
			$result[ 'output' ]
		);
	}

	/**
	 * @return array{exit_code:int,output:string}
	 */
	private function runClassifierScript( string $content, int $phpstanExitCode ) :array {
		$this->skipIfPackageScriptUnavailable();

		$tempFile = \tempnam( \sys_get_temp_dir(), 'shield-phpstan-' );
		$this->assertNotFalse( $tempFile );
		\file_put_contents( $tempFile, $content );

		try {
			$process = $this->runPhpScript(
				'bin/classify-packaged-phpstan.php',
				[
					$tempFile,
					(string)$phpstanExitCode,
				]
			);

			return [
				'exit_code' => $process->getExitCode() ?? 1,
				'output' => $process->getOutput().$process->getErrorOutput(),
			];
		}
		finally {
			@unlink( $tempFile );
		}
	}
}
