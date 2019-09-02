<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class CollateResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class CollateResults {

	use ModConsumer,
		HandlerConsumer;

	/**
	 * @param string $sScanSlug
	 */
	public function collate( $sScanSlug ) {
		/** @var ScanQueue\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
		/** @var ScanQueue\EntryVO[] $aAll */
		$aAll = $oSel->filterByScan( $sScanSlug )
					 ->setResultsAsVo( true )
					 ->query();
		$oAction = ( new ScanActionFromSlug() )->getAction( $sScanSlug );

		$oResultsSet = $oAction->getNewResultsSet();
		foreach ( $aAll as $oEntry ) {
			$oCurrent = clone $oAction;
			$oAction->applyFromArray( $oEntry->meta );
		}
	}
}
