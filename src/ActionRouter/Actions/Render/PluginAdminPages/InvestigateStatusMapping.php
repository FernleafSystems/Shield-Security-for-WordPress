<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

trait InvestigateStatusMapping {

	protected function mapCountToStatus( int $count, string $zeroStatus = 'info', string $nonZeroStatus = 'warning' ) :string {
		return $count > 0 ? $nonZeroStatus : $zeroStatus;
	}

	protected function highestStatus( array $statuses, string $defaultStatus = 'info' ) :string {
		$statuses = $this->normalizeStatuses( $statuses );
		return StatusPriority::highest( $statuses, $defaultStatus );
	}

	protected function mapFlagGroupToStatus( array $statuses, string $defaultStatus = 'info' ) :string {
		return $this->highestStatus( $statuses, $defaultStatus );
	}

	private function normalizeStatuses( array $statuses ) :array {
		$normalized = [];
		foreach ( $statuses as $status ) {
			$status = (string)$status;
			if ( $status !== '' ) {
				$normalized[] = $status;
			}
		}
		return $normalized;
	}
}
