<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ResultsUpdate
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results
 */
class ResultsUpdate {

	use Databases\Base\HandlerConsumer;
	use Scans\Common\ScanActionConsumer;

	/**
	 * @param Scans\Base\BaseResultsSet $oNewResults
	 */
	public function update( $oNewResults ) {
		$oAction = $this->getScanActionVO();
		$oNewCopy = clone $oNewResults; // so we don't modify these for later use.

		$oDbH = $this->getDbHandler();

		$oExisting = ( new ResultsRetrieve() )
			->setDbHandler( $oDbH )
			->setScanActionVO( $oAction )
			->retrieve();

		$oItemsToDelete = ( new Scans\Base\DiffResultForStorage() )->diff( $oExisting, $oNewCopy );
		( new ResultsDelete() )
			->setDbHandler( $oDbH )
			->delete( $oItemsToDelete );

		( new ResultsStore() )
			->setDbHandler( $oDbH )
			->setScanActionVO( $oAction )
			->store( $oNewCopy );

		$oUp = $oDbH->getQueryUpdater();
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
