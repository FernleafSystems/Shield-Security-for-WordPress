<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RunState {

	use PluginControllerConsumer;

	public function markBuilding( int $scanID ) :void {
		$now = Services::Request()->ts();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'status'          => 'building',
			'started_at'      => $now,
			'last_process_at' => $now,
		] );
	}

	public function markCompleted( int $scanID ) :void {
		$now = Services::Request()->ts();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'finished_at'     => $now,
			'status'          => 'completed',
			'last_process_at' => $now,
		] );
	}

	public function markFailed( int $scanID ) :void {
		$now = Services::Request()->ts();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'finished_at'     => $now,
			'status'          => 'failed',
			'last_process_at' => $now,
		] );
		$this->deleteUnfinishedItems( $scanID );
	}

	public function markRunning( int $scanID ) :void {
		$now = Services::Request()->ts();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'status'          => 'running',
			'started_at'      => $now,
			'last_process_at' => $now,
		] );
	}

	public function markUnfinishedRunsFailed() :void {
		$scanIDs = self::con()->db_con->scans->getQuerySelector()
					 ->filterByNotFinished()
					 ->getDistinctForColumn( 'id' );
		foreach ( \array_map( '\intval', \is_array( $scanIDs ) ? $scanIDs : [] ) as $scanID ) {
			if ( $scanID > 0 ) {
				$this->markFailed( $scanID );
			}
		}
	}

	public function deleteUnfinishedItems( int $scanID ) :void {
		self::con()->db_con->scan_items->getQueryDeleter()
			->filterByScan( $scanID )
			->filterByNotFinished()
			->query();
	}
}
