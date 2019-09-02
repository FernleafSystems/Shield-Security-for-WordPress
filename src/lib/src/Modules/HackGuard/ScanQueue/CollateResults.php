<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class CollateResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class CollateResults {

	use Databases\Base\HandlerConsumer;

	/**
	 * @param string $sScanSlug
	 * @return Scans\Base\BaseResultsSet|mixed|null
	 */
	public function collate( $sScanSlug ) {
		/** @var Databases\ScanQueue\Handler $oDbH */
		$oDbH = $this->getDbHandler();
		/** @var Databases\ScanQueue\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		$oSel->filterByScan( $sScanSlug )
			 ->setResultsAsVo( true );

		$oResultsSet = null;
		/** @var Databases\ScanQueue\EntryVO $oEntry */
		foreach ( $oSel->query() as $oEntry ) {
			$oAction = ( new ConvertBetweenTypes() )
				->setDbHandler( $oDbH )
				->fromDbEntryToAction( $oEntry );

			if ( empty( $oResultsSet ) ) {
				$oResultsSet = $oAction->getNewResultsSet();
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
