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
	 * @param Databases\ScanQueue\EntryVO $entry
	 * @return Scans\Base\BaseScanActionVO|mixed
	 */
	public function fromDbEntryToAction( $entry ) {
		$action = ScanActionFromSlug::GetAction( $entry->scan );
		$action->applyFromArray( $entry->meta );
		$action->items = $entry->items;
		$action->results = $entry->results;
		return $action;
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
