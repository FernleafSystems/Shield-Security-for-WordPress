<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ResultsStore {

	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\ResultsSet $resultsToStore
	 * @throws \Exception
	 */
	public function store( $resultsToStore ) {
		if ( !$resultsToStore->hasItems() ) {
			throw new \Exception( 'No items' );
		}

		$scanCon = $this->getScanController();
		$dbh = $scanCon->getScanResultsDbHandler();

		$VOs = ( new ConvertBetweenTypes() )
			->setScanController( $scanCon )
			->fromResultsToVOs( $resultsToStore );

		// Try to find all older, but deleted results.
		/** @var Databases\Scanner\Select $selector */
		$selector = $dbh->getQuerySelector();
		/** @var Databases\Scanner\EntryVO[] $existing */
		$existing = $selector->filterByScan( $scanCon->getSlug() )
							 ->filterByHashes( array_keys( $VOs ) )
							 ->setIncludeSoftDeleted( true )
							 ->query();

		if ( !empty( $existing ) ) {
			foreach ( $existing as $existingRecord ) {
				foreach ( $VOs as $vo ) {
					if ( $existingRecord->hash === $vo->hash ) {

						$updateData = $vo->getRawData();
						$updateData[ 'deleted_at' ] = 0;

						$dbh->getQueryUpdater()
							->setUpdateWheres( [
								'scan' => $scanCon->getSlug(),
								'hash' => $existingRecord->hash,
							] )
							->setUpdateData( $updateData )
							->query();
						unset( $VOs[ $vo->hash ] );
						break;
					}
				}
			}
		}

		foreach ( $VOs as $vo ) {
			$dbh->getQueryInserter()->insert( $vo );
		}
	}
}