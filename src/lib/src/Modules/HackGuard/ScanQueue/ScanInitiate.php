<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanInitiate
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ScanInitiate {

	use ModConsumer,
		QueueProcessorConsumer;

	/**
	 * Build and Enqueue.
	 * @param string $sSlug
	 * @throws \Exception
	 */
	public function init( $sSlug ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		if ( ( new IsScanEnqueued() )->setDbHandler( $oMod->getDbHandler_ScanQueue() )->check( $sSlug ) ) {
			throw new \Exception( 'Scan is already running' );
		}

		$oAction = ( new BuildScanAction() )
			->setMod( $oMod )
			->build( $sSlug );

		$oQ = $this->getQueueProcessor();
		( new ScanEnqueue() )
			->setMod( $oMod )
			->setQueueProcessor( $oQ )
			->setScanActionVO( $oAction )
			->enqueue();

		$oQ->dispatch();
	}
}
