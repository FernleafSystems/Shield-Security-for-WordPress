<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class SourceAssetBuildReadiness {

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	public function ensureReady(
		string $rootDir,
		?callable $onOutput = null,
		string $failureContext = 'browser tests'
	) :void {
		$command = [
			\PHP_BINARY,
			'./bin/run-node-tool.php',
			'webpack',
			'--config',
			'webpack.config.js',
			'--mode',
			'production',
		];

		$process = $this->processRunner->run( $command, $rootDir, $onOutput );
		$exitCode = $process->getExitCode() ?? 1;
		if ( $exitCode !== 0 ) {
			$errorOutput = \trim( $process->getErrorOutput() );
			$output = \trim( $process->getOutput() );
			throw new \RuntimeException(
				'Failed to rebuild browser assets for '.$failureContext.'.'
				.( $errorOutput !== '' ? ' '.$errorOutput : '' )
				.( $errorOutput === '' && $output !== '' ? ' '.$output : '' )
			);
		}
	}
}
