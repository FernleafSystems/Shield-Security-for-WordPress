<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerComposeExecutor;

class RecordingDockerComposeExecutor extends DockerComposeExecutor {

	/** @var array<int,array{root_dir:string,compose_files:array,sub_command:array,env_overrides:?array}> */
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
		string $rootDir,
		array $composeFiles,
		array $subCommand,
		?array $envOverrides = null
	) :int {
		$this->calls[] = [
			'root_dir' => $rootDir,
			'compose_files' => $composeFiles,
			'sub_command' => $subCommand,
			'env_overrides' => $envOverrides,
		];

		return (int)( \array_shift( $this->exitCodes ) ?? 0 );
	}
}
