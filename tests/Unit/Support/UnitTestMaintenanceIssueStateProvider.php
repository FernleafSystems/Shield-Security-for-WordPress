<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;

class UnitTestMaintenanceIssueStateProvider extends MaintenanceIssueStateProvider {

	private array $states;

	public function __construct( array $states ) {
		$this->states = $states;
	}

	public function buildStates() :array {
		return $this->states;
	}
}
