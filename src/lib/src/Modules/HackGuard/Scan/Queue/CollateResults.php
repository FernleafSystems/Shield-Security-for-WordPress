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
		/** @var Databases\ScanQueue\Handler $dbh */
		$dbh = $this->getDbHandler();
		/** @var Databases\ScanQueue\Select $selector */
		$selector = $dbh->getQuerySelector();
		$selector->filterByScan( $sScanSlug )
				 ->setResultsAsVo( true );
		$scanCon = $this->getScanController();

		$resultsSet = null;
		/** @var Databases\ScanQueue\EntryVO $entry */
		foreach ( $selector->query() as $entry ) {
			$action = ( new ConvertBetweenTypes() )
				->setDbHandler( $dbh )
				->fromDbEntryToAction( $entry );

			if ( empty( $resultsSet ) ) {
				$resultsSet = $scanCon->getNewResultsSet();
			}

			foreach ( $action->results as $aResItemData ) {
				$resultsSet->addItem(
					$action->getNewResultItem()->applyFromArray( $aResItemData )
				);
			}
		}

		return $resultsSet;
	}
}
