<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertBetweenTypes
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
class ConvertBetweenTypes {

	use Databases\Base\HandlerConsumer;

	/**
	 * @param Databases\ScanQueue\EntryVO $oEntry
	 * @return Scans\Base\BaseScanActionVO|mixed
	 */
	public function fromDbEntryToAction( $oEntry ) {
		$oScanAction = ( new ScanActionFromSlug() )->getAction( $oEntry->scan );
		$oScanAction->applyFromArray( $oEntry->meta );
		$oScanAction->items = $oEntry->items;
		$oScanAction->results = $oEntry->results;
		return $oScanAction;
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $oAction
	 * @return Databases\ScanQueue\EntryVO
	 */
	public function fromActionToDbEntry( $oAction ) {
		$oEntry = new Databases\ScanQueue\EntryVO();
		foreach ( $this->getDbHandler()->getColumnsDefinition() as $sField ) {
			if ( isset( $oAction->{$sField} ) ) {
				$oEntry->{$sField} = $oAction->{$sField};
			}
		}
		unset( $oAction->items );
		unset( $oAction->results );
		$oEntry->meta = $oAction->getRawDataAsArray();
		return $oEntry;
	}
}
