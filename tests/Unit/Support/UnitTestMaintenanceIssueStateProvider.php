<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;

class UnitTestMaintenanceIssueStateProvider extends MaintenanceIssueStateProvider {

	private array $states;

	public function __construct( array $states ) {
		$this->states = $states;
	}

	public function buildStates() :array {
		return \array_map(
			static function ( array $state ) :array {
				if ( !isset( $state[ 'drill_bucket' ] ) ) {
					$state[ 'drill_bucket' ] = 'review';
				}
				return $state;
			},
			$this->states
		);
	}
}
