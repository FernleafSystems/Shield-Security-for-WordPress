<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeRefresher;

class RecordingLocalSiteRuntimeRefresher extends LocalSiteRuntimeRefresher {

	/** @var array<int,array{root_dir:string,compose_files:array,service_name:string,env_overrides:array}> */
	public array $resolveCalls = [];

	/** @var array<int,array{root_dir:string,container_id:string,host_manifest:?array}> */
	public array $refreshCalls = [];

	/** @var string[] */
	private array $containerIds;

	/** @var string[] */
	private array $events = [];

	/**
	 * @param string[] $containerIds
	 * @param string[]|null $events
	 */
	public function __construct( array $containerIds = [ '' ], ?array &$events = null ) {
		parent::__construct();
		$this->containerIds = $containerIds;
		if ( $events === null ) {
			$events = [];
		}
		$this->events = &$events;
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

	public function refresh(
		string $rootDir,
		string $containerId,
		?callable $onOutput = null,
		?array $hostManifest = null
	) :void {
		$this->events[] = 'runtime-refresh';
		$this->refreshCalls[] = [
			'root_dir' => $rootDir,
			'container_id' => $containerId,
			'host_manifest' => $hostManifest,
		];
	}
}
