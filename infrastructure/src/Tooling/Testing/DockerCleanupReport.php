<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class DockerCleanupReport {

	private bool $dryRun;

	/** @var string[] */
	private array $plannedActions = [];

	/** @var string[] */
	private array $completedActions = [];

	/** @var string[] */
	private array $findings = [];

	public function __construct( bool $dryRun = false ) {
		$this->dryRun = $dryRun;
	}

	public function dryRun() :bool {
		return $this->dryRun;
	}

	public function addPlannedAction( string $action ) :void {
		$this->plannedActions[] = $action;
	}

	public function addCompletedAction( string $action ) :void {
		$this->completedActions[] = $action;
	}

	public function addFinding( string $finding ) :void {
		$this->findings[] = $finding;
	}

	public function merge( self $report ) :void {
		foreach ( $report->plannedActions() as $action ) {
			$this->addPlannedAction( $action );
		}
		foreach ( $report->completedActions() as $action ) {
			$this->addCompletedAction( $action );
		}
		foreach ( $report->findings() as $finding ) {
			$this->addFinding( $finding );
		}
	}

	/**
	 * @return string[]
	 */
	public function plannedActions() :array {
		return $this->plannedActions;
	}

	/**
	 * @return string[]
	 */
	public function completedActions() :array {
		return $this->completedActions;
	}

	/**
	 * @return string[]
	 */
	public function findings() :array {
		return $this->findings;
	}

	public function hasFindings() :bool {
		return $this->findings !== [];
	}
}
