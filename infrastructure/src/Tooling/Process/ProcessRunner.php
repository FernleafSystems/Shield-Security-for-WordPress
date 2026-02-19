<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Process;

use FernleafSystems\ShieldPlatform\Tooling\Output\LineEndingNormalizer;
use Symfony\Component\Process\Process;

class ProcessRunner {

	private LineEndingNormalizer $lineEndingNormalizer;

	public function __construct( ?LineEndingNormalizer $lineEndingNormalizer = null ) {
		$this->lineEndingNormalizer = $lineEndingNormalizer ?? new LineEndingNormalizer();
	}

	/**
	 * @param string[]       $command
	 * @param callable|null  $onOutput Receives (string $type, string $buffer)
	 */
	public function run( array $command, string $workingDir, ?callable $onOutput = null ) :Process {
		if ( !\is_dir( $workingDir ) ) {
			throw new \RuntimeException( \sprintf(
				'Working directory does not exist: %s',
				$workingDir
			) );
		}

		$process = new Process(
			$command,
			$workingDir,
			null,  // env - inherit from parent
			null,  // input
			null   // timeout - no limit
		);
		$process->setTimeout( null );
		$process->run( $onOutput ?? function ( string $type, string $buffer ) :void {
			$this->writeOutputBuffer( $type, $buffer );
		} );

		return $process;
	}

	/**
	 * Run a command and throw when it exits non-zero.
	 *
	 * @param string[]       $command
	 * @param callable|null  $onOutput Receives (string $type, string $buffer)
	 */
	public function runOrThrow( array $command, string $workingDir, ?callable $onOutput = null ) :Process {
		$process = $this->run( $command, $workingDir, $onOutput );
		$exitCode = $process->getExitCode() ?? 1;

		if ( $exitCode !== 0 ) {
			$errorOutput = \trim( $process->getErrorOutput() );
			$message = \sprintf(
				'Command failed with exit code %d: %s',
				$exitCode,
				\implode( ' ', $command )
			);
			if ( $errorOutput !== '' ) {
				$message .= "\nError output: ".$errorOutput;
			}
			throw new \RuntimeException( $message );
		}

		return $process;
	}

	private function writeOutputBuffer( string $type, string $buffer ) :void {
		$normalized = $this->lineEndingNormalizer->toHostEol( $buffer );

		if ( $type === Process::ERR ) {
			\fwrite( \STDERR, $normalized );
		}
		else {
			echo $normalized;
		}
	}
}
