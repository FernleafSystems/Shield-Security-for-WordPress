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
	 * @param string $scanSlug
	 * @return Scans\Base\ResultsSet|mixed
	 */
	public function collate( $scanSlug ) {
		/** @var Databases\ScanQueue\Handler $dbh */
		$dbh = $this->getDbHandler();
		/** @var Databases\ScanQueue\Select $selector */
		$selector = $dbh->getQuerySelector();
		$selector->filterByScan( $scanSlug )
				 ->setResultsAsVo( true );

		$resultsSet = $this->getScanController()->getNewResultsSet();

		/** @var Databases\ScanQueue\EntryVO $entry */
		foreach ( $selector->query() as $entry ) {
			$action = ( new ConvertBetweenTypes() )
				->setMod( $this->getScanController()->getMod() )
				->fromDbEntryToAction( $entry ); // TODO: this uses ->results which wont be available in new DB system

			foreach ( $action->results as $resultItemRawData ) {
				$resultsSet->addItem(
					$action->getNewResultItem()->applyFromArray( $resultItemRawData )
				);
			}
		}

		return $resultsSet;
	}
}
