<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ConvertBetweenTypes {

	use ModConsumer;

	/**
	 * @param ScanItemsDB\Ops\Record $scanItemsRecord
	 * @return Scans\Base\BaseScanActionVO|mixed
	 */
	public function fromDbEntryToAction( ScanItemsDB\Ops\Record $scanItemsRecord ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ScansDB\Ops\Select $select */
		$select = $mod->getDbH_Scans()->getQuerySelector();
		/** @var ScansDB\Ops\Record $scanRecord */
		$scanRecord = $select->byId( $scanItemsRecord->scan_ref );

		$action = ScanActionFromSlug::GetAction( $scanRecord->scan );
		$action->applyFromArray( $scanRecord->meta );
		$action->scan = $scanRecord->scan;
		$action->started_at = $scanRecord->started_at;
		$action->finished_at = $scanRecord->finished_at;
		$action->items = $scanItemsRecord->items;
		return $action;
	}

	/**
	 * @param Scans\Base\BaseScanActionVO $action
	 * @return Databases\ScanQueue\EntryVO
	 */
	public function fromActionToDbEntry( $action ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$entry = new Databases\ScanQueue\EntryVO();
		foreach ( $mod->getDbHandler_ScanQueue()->getTableSchema()->getColumnNames() as $field ) {
			if ( isset( $action->{$field} ) ) {
				$entry->{$field} = $action->{$field};
			}
		}
		unset( $action->items );
		unset( $action->results );
		$entry->meta = $action->getRawData();
		return $entry;
	}
}
