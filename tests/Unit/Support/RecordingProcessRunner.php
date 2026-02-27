<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class RecordingProcessRunner extends ProcessRunner {

	/** @var array<int,array{command:array,working_dir:string,env_overrides:?array,has_output_callback:bool}> */
	public array $calls = [];

	/** @var int[] */
	private array $exitCodes;

	/**
	 * @param int[] $exitCodes
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

		$exitCode = \array_shift( $this->exitCodes );
		$process = new Process(
			[
				\PHP_BINARY,
				'-r',
				'exit('.(int)( $exitCode ?? 0 ).');',
			]
		);
		$process->run( static function () :void {
		} );

		return $process;
	}
}
