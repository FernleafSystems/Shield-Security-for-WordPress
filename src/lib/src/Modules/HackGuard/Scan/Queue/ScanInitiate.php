<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanInitiate
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanInitiate {

	use ModConsumer;
	use QueueProcessorConsumer;

	/**
	 * Build and Enqueue.
	 * @param string $slug
	 * @throws \Exception
	 */
	public function init( string $slug ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbHandler_ScanQueue();

		if ( ( new IsScanEnqueued() )->setDbHandler( $dbh )->check( $slug ) ) {
			throw new \Exception( 'Scan is already running' );
		}

		$oAction = ( new BuildScanAction() )
			->setMod( $mod )
			->build( $slug );

		( new ScanEnqueue() )
			->setDbHandler( $dbh )
			->setQueueProcessor( $this->getQueueProcessor() )
			->setScanActionVO( $oAction )
			->enqueue();
	}
}
