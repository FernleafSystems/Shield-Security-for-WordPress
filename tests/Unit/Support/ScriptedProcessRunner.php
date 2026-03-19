<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class ScriptedProcessRunner extends ProcessRunner {

	/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
	public array $calls = [];

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

		$script = <<<'PHP'
$stdout = base64_decode($argv[1]);
$stderr = base64_decode($argv[2]);
if ( $stdout !== false && $stdout !== '' ) {
	fwrite(STDOUT, $stdout);
}
if ( $stderr !== false && $stderr !== '' ) {
	fwrite(STDERR, $stderr);
}
exit((int)$argv[3]);
PHP;

		$process = new Process(
			[
				\PHP_BINARY,
				'-r',
				$script,
				\base64_encode( $response[ 'stdout' ] ),
				\base64_encode( $response[ 'stderr' ] ),
				(string)$response[ 'exit_code' ],
			]
		);
		$process->run( static function () :void {
		} );

		return $process;
	}

	/**
	 * @param string[] $command
	 */
	private function simulateSideEffects( array $command, string $workingDir, int $exitCode ) :void {
		if ( $exitCode !== 0 || ( $command[ 0 ] ?? '' ) !== 'tar' ) {
			return;
		}

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
}
