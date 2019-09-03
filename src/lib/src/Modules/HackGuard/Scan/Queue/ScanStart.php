<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ScanStart
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ScanStart {

	use ModConsumer,
		HackGuard\Scan\Queue\Build\QueueBuilderConsumer;

	/**
	 * @param string|string[] $aScanSlugs
	 * @throws \Exception
	 */
	public function start( $aScanSlugs ) {
		if ( !is_array( $aScanSlugs ) ) {
			$aScanSlugs = [ $aScanSlugs ];
		}
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		foreach ( $aScanSlugs as $sSlug ) {
			$oOpts->addRemoveScanToBuild( $sSlug );
		}
		$this->getQueueBuilder()->dispatch();
	}
}
