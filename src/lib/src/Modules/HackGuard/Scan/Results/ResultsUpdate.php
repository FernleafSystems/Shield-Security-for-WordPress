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
	 * @param Scans\Base\ResultsSet $oNewResults
	 */
	public function update( $oNewResults ) {
		$oSCon = $this->getScanController();
		$oNewCopy = clone $oNewResults; // so we don't modify these for later use.

		$oExisting = ( new ResultsRetrieve() )
			->setScanController( $oSCon )
			->retrieve();

		$oItemsToDelete = ( new Scans\Base\DiffResultForStorage() )->diff( $oExisting, $oNewCopy );
		( new ResultsDelete() )
			->setScanController( $oSCon )
			->delete( $oItemsToDelete );

		( new ResultsStore() )
			->setScanController( $oSCon )
			->store( $oNewCopy );

		$oUp = $oSCon->getScanResultsDbHandler()->getQueryUpdater();
		/** @var Databases\Scanner\EntryVO $oVo */
		$oConverter = ( new ConvertBetweenTypes() )->setScanController( $oSCon );
		foreach ( $oConverter->fromResultsToVOs( $oExisting ) as $oVo ) {
			$oUp->reset()
				->setUpdateData( $oVo->getRawData() )
				->setUpdateWheres(
					[
						'scan' => $oSCon->getSlug(),
						'hash' => $oVo->hash,
					]
				)
				->query();
		}
	}
}
