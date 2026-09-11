<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class RecordingProcessRunner extends ProcessRunner {

	/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
	public array $calls = [];

	/**
	 * @var array<int,int|array{exit_code:int,junit_class?:string,junit_tests?:int,stdout?:string,stderr?:string}>
	 */
	private array $exitCodes;

	private bool $failWhenExhausted = false;

	/**
	 * @param array<int,int|array{exit_code:int,junit_class?:string,junit_tests?:int,stdout?:string,stderr?:string}> $exitCodes
	 */
	public function __construct( array $exitCodes = [ 0 ] ) {
		parent::__construct();
		$this->exitCodes = $exitCodes;
	}

	/**
	 * @param array<int,int|array{exit_code:int,junit_class?:string,junit_tests?:int,stdout?:string,stderr?:string}> $exitCodes
	 */
	public static function strict( array $exitCodes ) :self {
		$runner = new self( $exitCodes );
		$runner->failWhenExhausted = true;
		return $runner;
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

		return $this->buildProcessFromQueue( $command, $onOutput );
	}

	/** @param string[] $command */
	private function buildProcessFromQueue( array $command, ?callable $onOutput = null ) :Process {
		if ( $this->failWhenExhausted && $this->exitCodes === [] ) {
			throw new \LogicException( 'Unexpected process call exhausted the configured response queue.' );
		}

		$queueEntry = \array_shift( $this->exitCodes );
		$exitCode = \is_array( $queueEntry ) ? (int)( $queueEntry[ 'exit_code' ] ?? 0 ) : (int)( $queueEntry ?? 0 );
		$stdout = \is_array( $queueEntry ) ? (string)( $queueEntry[ 'stdout' ] ?? '' ) : '';
		$stderr = \is_array( $queueEntry ) ? (string)( $queueEntry[ 'stderr' ] ?? '' ) : '';
		if ( \is_array( $queueEntry ) && isset( $queueEntry[ 'junit_tests' ] ) ) {
			$junitIndex = \array_search( '--log-junit', $command, true );
			if ( \is_int( $junitIndex ) && isset( $command[ $junitIndex + 1 ] ) ) {
				$class = (string)( $queueEntry[ 'junit_class' ] ?? 'RecordedTest' );
				\file_put_contents(
					$command[ $junitIndex + 1 ],
					\sprintf( '<?xml version="1.0"?><testsuites><testsuite tests="%d" assertions="0" errors="0" failures="0" skipped="0" time="0"><testcase classname="%s"/></testsuite></testsuites>', $queueEntry[ 'junit_tests' ], $class )
				);
			}
		}
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
