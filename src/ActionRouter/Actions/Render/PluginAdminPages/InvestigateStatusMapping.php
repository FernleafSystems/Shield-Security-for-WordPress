<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

trait InvestigateStatusMapping {

	protected function mapCountToStatus( int $count, string $zeroStatus = 'info', string $nonZeroStatus = 'warning' ) :string {
		return $count > 0 ? $nonZeroStatus : $zeroStatus;
	}

	protected function highestStatus( array $statuses, string $defaultStatus = 'info' ) :string {
		$statuses = \array_values( \array_filter( \array_map( '\strval', $statuses ), '\strlen' ) );
		return StatusPriority::highest( $statuses, $defaultStatus );
	}

	protected function mapFlagGroupToStatus( array $statuses, string $defaultStatus = 'info' ) :string {
		return $this->highestStatus( $statuses, $defaultStatus );
	}
}

