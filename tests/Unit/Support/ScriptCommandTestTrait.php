<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use Symfony\Component\Process\Process;

trait ScriptCommandTestTrait {

	protected function skipIfPackageScriptUnavailable( string $message = 'bin/ directory is excluded from packages (development-only)' ) :void {
		if ( \method_exists( $this, 'isTestingPackage' ) && $this->isTestingPackage() ) {
			$this->markTestSkipped( $message );
		}
	}

	protected function assertPhpScriptSyntaxValid( string $relativePath ) :void {
		$scriptPath = $this->getPluginFilePath( $relativePath );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame(
			0,
			$returnCode,
			$relativePath.' should have valid PHP syntax: '.\implode( "\n", $output )
		);
	}

	/**
	 * @param string[]             $args
	 * @param array<string,string> $env
	 */
	protected function runPhpScript( string $relativePath, array $args = [], array $env = [] ) :Process {
		$command = \array_merge(
			[ \PHP_BINARY, $this->getPluginFilePath( $relativePath ) ],
			$args
		);

		return $this->runProcess( $command, $env );
	}

	/**
	 * @param string[]             $command
	 * @param array<string,string> $env
	 */
	protected function runProcess( array $command, array $env = [] ) :Process {
		$process = new Process( $command, $this->getPluginRoot(), $env );
		$process->run();
		return $process;
	}

	protected function processOutput( Process $process ) :string {
		return $process->getOutput().$process->getErrorOutput();
	}
}
