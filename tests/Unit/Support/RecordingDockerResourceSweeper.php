<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerResourceSweeper;
use FernleafSystems\ShieldPlatform\Tooling\Testing\DockerCleanupReport;

class RecordingDockerResourceSweeper extends DockerResourceSweeper {

	/** @var string[] */
	public array $startupSweepCalls = [];

	/** @var array<int,array{root_dir:string,run_id:string,lane_count:int,full_cleanup:bool,dry_run:bool}> */
	public array $cleanupRunResourcesCalls = [];

	/** @var array<int,array{container_run_id:string,container_lifecycle:string,lane:string,container_expires_at:string,volume_run_id:string,volume_lifecycle:string,volume_expires_at:string}> */
	public array $labelEnvironmentCalls = [];

	/** @var string[] */
	private array $cleanupFindings;

	/**
	 * @param string[] $cleanupFindings
	 */
	public function __construct( array $cleanupFindings = [] ) {
		$this->cleanupFindings = $cleanupFindings;
	}

	public function startupSweep( string $rootDir ) :void {
		$this->startupSweepCalls[] = $rootDir;
	}

	public function cleanupRunResources( string $rootDir, string $runId, int $laneCount, bool $fullCleanup, bool $dryRun = false ) :DockerCleanupReport {
		$this->cleanupRunResourcesCalls[] = [
			'root_dir' => $rootDir,
			'run_id' => $runId,
			'lane_count' => $laneCount,
			'full_cleanup' => $fullCleanup,
			'dry_run' => $dryRun,
		];

		$report = new DockerCleanupReport( $dryRun );
		foreach ( $this->cleanupFindings as $finding ) {
			$report->addFinding( $finding );
		}

		return $report;
	}

	public function labelEnvironment(
		string $containerRunId,
		string $containerLifecycle,
		string $lane,
		string $containerExpiresAt,
		string $volumeRunId,
		string $volumeLifecycle,
		string $volumeExpiresAt
	) :array {
		$this->labelEnvironmentCalls[] = [
			'container_run_id' => $containerRunId,
			'container_lifecycle' => $containerLifecycle,
			'lane' => $lane,
			'container_expires_at' => $containerExpiresAt,
			'volume_run_id' => $volumeRunId,
			'volume_lifecycle' => $volumeLifecycle,
			'volume_expires_at' => $volumeExpiresAt,
		];

		return parent::labelEnvironment(
			$containerRunId,
			$containerLifecycle,
			$lane,
			$containerExpiresAt,
			$volumeRunId,
			$volumeLifecycle,
			$volumeExpiresAt
		);
	}
}
