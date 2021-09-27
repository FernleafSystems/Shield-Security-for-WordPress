<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ResultsDelete {

	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\ResultsSet $resultsToDelete
	 * @param bool                  $softDelete
	 * @return bool
	 * @throws \Exception
	 */
	public function delete( $resultsToDelete, bool $softDelete = true ) {
		if ( !$resultsToDelete->hasItems() ) {
			throw new \Exception( 'No items' );
		}

		$hashes = array_filter( array_map(
			function ( $item ) {
				return (string)$item->hash;
			},
			$resultsToDelete->getAllItems()
		) );

		$success = true;
		if ( !empty( $hashes ) ) {
			if ( $softDelete ) {
				$success = $this->softDelete( $hashes );
			}
			else {
				/** @var Databases\Scanner\Delete $deleter */
				$deleter = $this->getScanController()
								->getScanResultsDbHandler()
								->getQueryDeleter();
				$success = $deleter->filterByHashes( $hashes )->query();
			}
		}
		return $success;
	}

	protected function softDelete( array $hashes ) :bool {
		/** @var Databases\Scanner\Update $updater */
		$updater = $this->getScanController()
						->getScanResultsDbHandler()
						->getQueryUpdater();
		// This is an inefficient hack for multiple soft-deletes until we rewrite a custom SQL updater
		foreach ( $hashes as $hash ) {
			$updater->setSoftDeleted()
					->setUpdateWheres( [
						'hash' => $hash
					] )
					->query();
		}

		return true;
	}

	/**
	 * @return $this
	 */
	public function deleteAllForScan() {
		/** @var Databases\Scanner\Delete $deleter */
		$deleter = $this->getScanController()
						->getScanResultsDbHandler()
						->getQueryDeleter();
		$deleter->forScan( $this->getScanController()->getSlug() );
		return $this;
	}
}
