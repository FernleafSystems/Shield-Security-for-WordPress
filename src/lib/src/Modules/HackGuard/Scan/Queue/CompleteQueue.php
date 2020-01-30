<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CompleteQueue
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class CompleteQueue {

	use Databases\Base\HandlerConsumer;
	use ModConsumer;

	/**
	 * Take care here not to confuse the 2x DB Handlers
	 */
	public function complete() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Databases\ScanQueue\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		$oSel = $oDbH->getQuerySelector();

		$aScansToNotify = [];
		foreach ( $oSel->getDistinctForColumn( 'scan' ) as $sScanSlug ) {

			$oScanCon = $oMod->getScanCon( $sScanSlug );

			$oResultsSet = ( new CollateResults() )
				->setScanController( $oScanCon )
				->setDbHandler( $oDbH )
				->collate( $sScanSlug );

			$this->getCon()->fireEvent( $sScanSlug.'_scan_run' );

			if ( $oResultsSet instanceof Scans\Base\BaseResultsSet ) {
				( new HackGuard\Scan\Results\ResultsUpdate() )
					->setScanController( $oScanCon )
					->update( $oResultsSet );

				if ( $oResultsSet->countItems() > 0 ) {
					$this->getCon()->fireEvent( $sScanSlug.'_scan_found' );
					$aScansToNotify[] = $sScanSlug;
				}
			}

			/** @var Databases\ScanQueue\Delete $oDel */
			$oDel = $oDbH->getQueryDeleter();
			$oDel->filterByScan( $sScanSlug )
				 ->query();
		}

		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isScanCron() && !empty( $aScansToNotify ) && !wp_next_scheduled( $oMod->prefix( 'post_scan' ) ) ) {
			wp_schedule_single_event(
				Services::Request()->ts() + 30,
				$oMod->prefix( 'post_scan' ),
				[ $aScansToNotify ]
			);
		}
		$oOpts->setIsScanCron( false );
	}
}
