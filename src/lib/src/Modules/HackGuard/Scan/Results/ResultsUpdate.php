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

	use Scans\Common\ScanActionConsumer;
	use ScanControllerConsumer;

	/**
	 * @param Scans\Base\BaseResultsSet $oNewResults
	 */
	public function update( $oNewResults ) {
		$oSCon = $this->getScanController();
		$oAction = $this->getScanActionVO();
		$oNewCopy = clone $oNewResults; // so we don't modify these for later use.

		$oExisting = ( new ResultsRetrieve() )
			->setScanController( $oSCon )
			->retrieve();

		$oItemsToDelete = ( new Scans\Base\DiffResultForStorage() )->diff( $oExisting, $oNewCopy );
		( new ResultsDelete() )
			->setScanController( $this->getScanController() )
			->delete( $oItemsToDelete );

		( new ResultsStore() )
			->setScanController( $this->getScanController() )
			->store( $oNewCopy );

		$oUp = $oSCon->getScanResultsDbHandler()->getQueryUpdater();
		/** @var Databases\Scanner\EntryVO $oVo */
		$oConverter = ( new ConvertBetweenTypes() )->setScanActionVO( $oAction );
		foreach ( $oConverter->fromResultsToVOs( $oExisting ) as $oVo ) {
			$oUp->reset()
				->setUpdateData( $oVo->getRawDataAsArray() )
				->setUpdateWheres(
					[
						'scan' => $oAction->scan,
						'hash' => $oVo->hash,
					]
				)
				->query();
		}
	}
}
