<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class RecordingProcessRunner extends ProcessRunner {

	/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
	public array $calls = [];

	/**
	 * @var array<int,int|array{exit_code:int,stdout?:string,stderr?:string}>
	 */
	private array $exitCodes;

	/**
	 * @param array<int,int|array{exit_code:int,stdout?:string,stderr?:string}> $exitCodes
	 */
	public function __construct( array $exitCodes = [ 0 ] ) {
		parent::__construct();
		$this->exitCodes = $exitCodes;
	}

	public function run(
		array $command,
		string $workingDir,
		?callable $onOutput = null,
		?array $envOverrides = null
	) :Process {
		$this->calls[] = [
			'command' => $command,
			'working_dir' => $workingDir,
			'env_overrides' => $envOverrides,
			'has_output_callback' => $onOutput !== null,
		];

		return $this->buildProcessFromQueue( $onOutput );
	}

	private function buildProcessFromQueue( ?callable $onOutput = null ) :Process {
		$queueEntry = \array_shift( $this->exitCodes );
		$exitCode = \is_array( $queueEntry ) ? (int)( $queueEntry[ 'exit_code' ] ?? 0 ) : (int)( $queueEntry ?? 0 );
		$stdout = \is_array( $queueEntry ) ? (string)( $queueEntry[ 'stdout' ] ?? '' ) : '';
		$stderr = \is_array( $queueEntry ) ? (string)( $queueEntry[ 'stderr' ] ?? '' ) : '';
		if ( $onOutput !== null ) {
			if ( $stdout !== '' ) {
				$onOutput( Process::OUT, $stdout );
			}
			if ( $stderr !== '' ) {
				$onOutput( Process::ERR, $stderr );
			}
		}

		return new RecordingProcess( $exitCode, $stdout, $stderr );
	}
}

class RecordingProcess extends Process {

	private int $recordedExitCode;

	private string $recordedOutput;

	private string $recordedErrorOutput;

	public function __construct( int $exitCode, string $output = '', string $errorOutput = '' ) {
		parent::__construct( [ \PHP_BINARY, '-v' ] );
		$this->recordedExitCode = $exitCode;
		$this->recordedOutput = $output;
		$this->recordedErrorOutput = $errorOutput;
	}

	public function getExitCode() :?int {
		return $this->recordedExitCode;
	}

	public function getOutput() :string {
		return $this->recordedOutput;
	}

	public function getErrorOutput() :string {
		return $this->recordedErrorOutput;
	}
}
