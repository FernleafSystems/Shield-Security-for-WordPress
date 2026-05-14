<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceGeneratedConfigReadiness;

class RecordingSourceGeneratedConfigReadiness extends SourceGeneratedConfigReadiness {

	/** @var array<int,array{root_dir:string,has_output_callback:bool,failure_context:string}> */
	public array $calls = [];

	/** @var string[] */
	private array $events = [];

	/**
	 * @param string[]|null $events
	 */
	public function __construct( ?array &$events = null ) {
		if ( $events === null ) {
			$events = [];
		}
		$this->events = &$events;
	}

	public function ensureReady(
		string $rootDir,
		?callable $onOutput = null,
		string $failureContext = 'source tooling'
	) :void {
		$this->events[] = 'generated-config';
		$this->calls[] = [
			'root_dir' => $rootDir,
			'has_output_callback' => $onOutput !== null,
			'failure_context' => $failureContext,
		];
	}
}
