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
		$oScanAction = ScanActionFromSlug::GetAction( $oEntry->scan );
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
		$entry = new Databases\ScanQueue\EntryVO();
		foreach ( $this->getDbHandler()->getTableSchema()->getColumnNames() as $field ) {
			if ( isset( $oAction->{$field} ) ) {
				$entry->{$field} = $oAction->{$field};
			}
		}
		unset( $oAction->items );
		unset( $oAction->results );
		$entry->meta = $oAction->getRawData();
		return $entry;
	}
}
