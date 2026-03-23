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
		$script = 'fwrite(STDOUT, '.\var_export( $stdout, true ).');'
			.'fwrite(STDERR, '.\var_export( $stderr, true ).');'
			.'exit('.$exitCode.');';
		$process = new Process(
			[
				\PHP_BINARY,
				'-r',
				$script,
			]
		);
		$process->run( static function () :void {
		} );
		if ( $onOutput !== null ) {
			if ( $stdout !== '' ) {
				$onOutput( Process::OUT, $stdout );
			}
			if ( $stderr !== '' ) {
				$onOutput( Process::ERR, $stderr );
			}
		}

		return $process;
	}
}
