<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class QueueInit {

	use PluginControllerConsumer;

	/**
	 * Build and Enqueue.
	 * @throws \Exception
	 */
	public function init( int $scanID ) {
		$this->preInit();
		$this->createScans( $scanID );
	}

	private function preInit() {
		( new CleanQueue() )->execute();
	}

	/**
	 * @throws \Exception
	 */
	private function createScans( int $scanID ) {
		$scanRecord = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
		if ( empty( $scanRecord ) ) {
			throw new \Exception( sprintf( 'Scan record %d could not be loaded.', $scanID ) );
		}

		( new RunState() )->markBuilding( $scanID );

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( self::con()->comps->scans->getScanCon( $scanRecord->scan ) )
			->run();
	}
}
