<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceAssetBuildReadiness;

class RecordingSourceAssetBuildReadiness extends SourceAssetBuildReadiness {

	/** @var array<int,array{root_dir:string,has_output_callback:bool,failure_context:string}> */
	public array $calls = [];

	/** @var string[] */
	private array $events = [];

	private ?\Throwable $throwable;

	/**
	 * @param string[]|null $events
	 */
	public function __construct( ?array &$events = null, ?\Throwable $throwable = null ) {
		if ( $events === null ) {
			$events = [];
		}
		$this->events = &$events;
		$this->throwable = $throwable;
	}

	public function ensureReady(
		string $rootDir,
		?callable $onOutput = null,
		string $failureContext = 'browser tests'
	) :void {
		$this->events[] = 'asset-build';
		$this->calls[] = [
			'root_dir' => $rootDir,
			'has_output_callback' => $onOutput !== null,
			'failure_context' => $failureContext,
		];
		if ( $this->throwable !== null ) {
			throw $this->throwable;
		}
	}
}
