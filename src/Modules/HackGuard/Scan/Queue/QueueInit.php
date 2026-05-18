<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class QueueInit {

	use PluginControllerConsumer;

	/**
	 * Build and Enqueue.
	 * @throws \Exception
	 */
	public function init( int $scanID ) :bool {
		$scanRecord = $this->loadQueuedScan( $scanID );
		if ( empty( $scanRecord ) ) {
			return false;
		}
		$this->createScans( $scanRecord );
		return true;
	}

	/**
	 * @throws \Exception
	 */
	private function createScans( ScansDB\Record $scanRecord ) :void {
		( new RunState() )->markBuilding( $scanRecord );

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( self::con()->comps->scans->getScanCon( $scanRecord->scan ) )
			->run();
	}

	private function loadQueuedScan( int $scanID ) :?ScansDB\Record {
		$scanRecord = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		if ( empty( $scanRecord ) ) {
			throw new \Exception( sprintf( 'Scan record %d could not be loaded.', $scanID ) );
		}

		if ( $scanRecord->status !== ScanStatus::QUEUED || (int)$scanRecord->finished_at > 0 ) {
			error_log( \sprintf(
				'Shield scan build skipped: scan_id=%d status=%s finished_at=%d',
				$scanID,
				(string)$scanRecord->status,
				(int)$scanRecord->finished_at
			) );
			return null;
		}

		return $scanRecord;
	}
}
