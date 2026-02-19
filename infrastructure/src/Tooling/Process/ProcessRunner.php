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

