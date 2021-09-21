<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsUpdate
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsUpdate {

	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\ResultsSet $newResults
	 */
	public function update( $newResults ) {
		$scanCon = $this->getScanController();
		$newCopy = clone $newResults; // so we don't modify these for later use.

		$existing = ( new ResultsRetrieve() )
			->setScanController( $scanCon )
			->retrieve();

		$itemsToDelete = ( new Scans\Base\DiffResultForStorage() )->diff( $existing, $newCopy );

		try {
			( new ResultsDelete() )
				->setScanController( $scanCon )
				->delete( $itemsToDelete );
		}
		catch ( \Exception $e ) {
		}

		try {
			( new ResultsStore() )
				->setScanController( $scanCon )
				->store( $newCopy );
		}
		catch ( \Exception $e ) {
		}

		if ( $existing->hasItems() ) {
			$updater = $scanCon->getScanResultsDbHandler()->getQueryUpdater();
			/** @var Databases\Scanner\EntryVO $vo */
			$converter = ( new ConvertBetweenTypes() )->setScanController( $scanCon );
			foreach ( $converter->fromResultsToVOs( $existing ) as $vo ) {
				$updater->reset()
						->setUpdateWheres( [
							'scan' => $scanCon->getSlug(),
							'hash' => $vo->hash,
						] )
						->setUpdateData( $vo->getRawData() )
						->query();
			}
		}
	}
}
