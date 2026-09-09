<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class ScriptedProcessRunner extends ProcessRunner {

	/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
	public array $calls = [];

	/** @var array<int,list<string>> */
	public array $tarFileLists = [];

	/** @var array<int,array{source:string,target:string,contents:string}> */
	public array $copiedFiles = [];

	/** @var array<int,array{exit_code:int,stdout:string,stderr:string}> */
	private array $responses;

	/**
	 * @param array<int,array{exit_code:int,stdout?:string,stderr?:string}> $responses
	 */
	public function __construct( array $responses ) {
		parent::__construct();
		$this->responses = \array_map(
			static fn( array $response ) :array => [
				'exit_code' => (int)( $response[ 'exit_code' ] ?? 0 ),
				'stdout' => (string)( $response[ 'stdout' ] ?? '' ),
				'stderr' => (string)( $response[ 'stderr' ] ?? '' ),
			],
			$responses
		);
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

		$response = \array_shift( $this->responses ) ?? [
			'exit_code' => 0,
			'stdout' => '',
			'stderr' => '',
		];
		$this->simulateSideEffects( $command, $workingDir, $response[ 'exit_code' ] );

		return new ScriptedProcess( $response[ 'exit_code' ], $response[ 'stdout' ], $response[ 'stderr' ] );
	}

	/**
	 * @param string[] $command
	 */
	private function simulateSideEffects( array $command, string $workingDir, int $exitCode ) :void {
		if ( $exitCode !== 0 ) {
			return;
		}
		if ( ( $command[ 0 ] ?? '' ) === 'docker' && ( $command[ 1 ] ?? '' ) === 'cp' ) {
			$this->captureDockerCopyPayload( $command, $workingDir );
			return;
		}
		if ( ( $command[ 0 ] ?? '' ) !== 'tar' ) {
			return;
		}
		$this->captureTarFileList( $command, $workingDir );

		$outputIndex = \array_search( '-cf', $command, true );
		if ( $outputIndex === false || !isset( $command[ $outputIndex + 1 ] ) ) {
			return;
		}

		$archivePath = Path::join( $workingDir, $command[ $outputIndex + 1 ] );
		$archiveDir = \dirname( $archivePath );
		if ( !\is_dir( $archiveDir ) ) {
			\mkdir( $archiveDir, 0777, true );
		}
		\file_put_contents( $archivePath, 'tar' );
	}

	/**
	 * @param string[] $command
	 */
	private function captureTarFileList( array $command, string $workingDir ) :void {
		$listIndex = \array_search( '-T', $command, true );
		if ( $listIndex === false || !isset( $command[ $listIndex + 1 ] ) ) {
			return;
		}

		$listPath = Path::join( $workingDir, $command[ $listIndex + 1 ] );
		if ( !\is_file( $listPath ) ) {
			return;
		}

		$this->tarFileLists[] = \array_values( \array_filter(
			\explode( "\n", \trim( (string)\file_get_contents( $listPath ) ) ),
			static fn( string $path ) :bool => $path !== ''
		) );
	}

	/**
	 * @param string[] $command
	 */
	private function captureDockerCopyPayload( array $command, string $workingDir ) :void {
		if ( !isset( $command[ 2 ], $command[ 3 ] ) ) {
			return;
		}
		$sourcePath = Path::join( $workingDir, $command[ 2 ] );
		if ( !\is_file( $sourcePath ) ) {
			return;
		}

		$this->copiedFiles[] = [
			'source' => $command[ 2 ],
			'target' => $command[ 3 ],
			'contents' => (string)\file_get_contents( $sourcePath ),
		];
	}
}

class ScriptedProcess extends Process {

	private int $scriptedExitCode;

	private string $scriptedOutput;

	private string $scriptedErrorOutput;

	public function __construct( int $exitCode, string $output = '', string $errorOutput = '' ) {
		parent::__construct( [ \PHP_BINARY, '-v' ] );
		$this->scriptedExitCode = $exitCode;
		$this->scriptedOutput = $output;
		$this->scriptedErrorOutput = $errorOutput;
	}

	public function getExitCode() :?int {
		return $this->scriptedExitCode;
	}

	public function getOutput() :string {
		return $this->scriptedOutput;
	}

	public function getErrorOutput() :string {
		return $this->scriptedErrorOutput;
	}
}
