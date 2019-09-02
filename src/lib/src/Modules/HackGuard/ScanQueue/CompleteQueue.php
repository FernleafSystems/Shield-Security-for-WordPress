<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class CompleteQueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class CompleteQueue {

	use ModConsumer,
		Databases\Base\HandlerConsumer;

	/**
	 * Take care here not to confuse the 2x DB Handlers
	 */
	public function complete() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbH = $this->getDbHandler();
		$oSel = $oDbH->getQuerySelector();

		/** @var Databases\Scanner\Handler $oDbH */
		$oDbHResults = $oMod->getDbHandler();
		foreach ( $oSel->getDistinctForColumn( 'scan' ) as $sScanSlug ) {

			$oAction = ( new Scan\ScanActionFromSlug() )->getAction( $sScanSlug );

			$oResultsSet = ( new CollateResults() )
				->setDbHandler( $oDbH )
				->collate( $sScanSlug );

			$this->getCon()->fireEvent( $oAction->scan.'_scan_run' );

			if ( $oResultsSet instanceof Scans\Base\BaseResultsSet ) {
				( new Scan\Results\ResultsUpdate() )
					->setDbHandler( $oDbHResults )
					->setScanActionVO( $oAction )
					->update( $oResultsSet );

				if ( $oResultsSet->countItems() > 0 ) {
					$this->getCon()->fireEvent( $oAction->scan.'_scan_found' );
				}
			}

			/** @var Databases\ScanQueue\Delete $oDel */
			$oDel = $oDbH->getQueryDeleter();
			$oDel->filterByScan( $sScanSlug )
				 ->query();
		}
	}
}
