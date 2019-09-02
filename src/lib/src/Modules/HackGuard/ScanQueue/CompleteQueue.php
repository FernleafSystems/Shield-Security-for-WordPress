<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class CompleteQueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class CompleteQueue {

	use ModConsumer,
		HandlerConsumer;

	/**
	 * @return Scans\Base\BaseResultsSet|mixed
	 */
	public function complete() {
		/** @var ScanQueue\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		$oSel = $oDbH->getQuerySelector();

		foreach ( $oSel->getDistinctForColumn( 'scan' ) as $sScanSlug ) {
			$oResultsSet = ( new CollateResults() )
				->setDbHandler( $this->getDbHandler() )
				->collate( $sScanSlug );

			if ( $oResultsSet instanceof Scans\Base\BaseResultsSet ) {
			}
		}
	}
}
