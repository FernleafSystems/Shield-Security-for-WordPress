<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\CreateNewScan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanInitiate {

	use ModConsumer;
	use QueueProcessorConsumer;

	/**
	 * Build and Enqueue.
	 * @param string $slug
	 * @throws \Exception
	 */
	public function init( string $slug ) {
		$this->initNew( $slug );
	}

	/**
	 * @throws \Exception
	 */
	private function initNew( string $slug ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$scanRecord = ( new CreateNewScan() )
			->setMod( $mod )
			->run( $slug );
		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $mod->getScanCon( $slug ) )
			->run();
	}

	/**
	 * @throws \Exception
	 */
	private function initOld( string $slug ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_ScanQueue();
		if ( ( new IsScanEnqueued() )->setDbHandler( $dbh )->check( $slug ) ) {
			throw new \Exception( 'Scan is already running' );
		}

		$action = ( new BuildScanAction() )
			->setMod( $mod )
			->build( $slug );

		( new ScanEnqueue() )
			->setMod( $this->getMod() )
			->setQueueProcessor( $this->getQueueProcessor() )
			->setScanActionVO( $action )
			->enqueue();
	}
}
