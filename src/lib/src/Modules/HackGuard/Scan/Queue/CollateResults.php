<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class CollateResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class CollateResults {

	use Databases\Base\HandlerConsumer;
	use ScanControllerConsumer;

	/**
	 * @param string $sScanSlug
	 * @return Scans\Base\ResultsSet|mixed|null
	 */
	public function collate( $sScanSlug ) {
		/** @var Databases\ScanQueue\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		/** @var Databases\ScanQueue\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		$oSel->filterByScan( $sScanSlug )
			 ->setResultsAsVo( true );
		$oSCon = $this->getScanController();

		$oResultsSet = null;
		/** @var Databases\ScanQueue\EntryVO $oEntry */
		foreach ( $oSel->query() as $oEntry ) {
			$oAction = ( new ConvertBetweenTypes() )
				->setDbHandler( $oDbH )
				->fromDbEntryToAction( $oEntry );

			if ( empty( $oResultsSet ) ) {
				$oResultsSet = $oSCon->getNewResultsSet();
			}

			foreach ( $oAction->results as $aResItemData ) {
				$oResultsSet->addItem(
					$oAction->getNewResultItem()->applyFromArray( $aResItemData )
				);
			}
		}

		return $oResultsSet;
	}
}
