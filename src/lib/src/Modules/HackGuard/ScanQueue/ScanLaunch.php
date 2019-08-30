<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class LaunchScan
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class ScanLaunch {

	use ModConsumer,
		QueueProcessorConsumer;

	/**
	 * @param string $sSlug
	 * @throws \Exception
	 */
	public function launch( $sSlug ) {
		$oAction = ( new BuildScanAction() )
			->setMod( $this->getMod() )
			->build( $sSlug );
		( new ScanEnqueue() )
			->setMod( $this->getMod() )
			->setQueueProcessor( $this->getQueueProcessor() )
			->setScanActionVO( $oAction )
			->enqueue();
	}
}
