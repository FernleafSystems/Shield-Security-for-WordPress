<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;

class RecordingLocalSiteRuntimeRefresher extends LocalSiteRuntimeRefresher {

	/** @var array<int,array{root_dir:string,compose_files:array,service_name:string,env_overrides:array}> */
	public array $resolveCalls = [];

	/** @var array<int,array{root_dir:string,container_id:string}> */
	public array $refreshCalls = [];

	/** @var string[] */
	private array $containerIds;

	/**
	 * @param string[] $containerIds
	 */
	public function __construct( array $containerIds = [ '' ] ) {
		parent::__construct();
		$this->containerIds = $containerIds;
	}

	public function resolveServiceContainerId(
		string $rootDir,
		array $composeFiles,
		string $serviceName,
		array $envOverrides
	) :string {
		$this->resolveCalls[] = [
			'root_dir' => $rootDir,
			'compose_files' => $composeFiles,
			'service_name' => $serviceName,
			'env_overrides' => $envOverrides,
		];

		return (string)( \array_shift( $this->containerIds ) ?? '' );
	}

	public function refresh( string $rootDir, string $containerId, ?callable $onOutput = null ) :void {
		$this->refreshCalls[] = [
			'root_dir' => $rootDir,
			'container_id' => $containerId,
		];
	}
}
