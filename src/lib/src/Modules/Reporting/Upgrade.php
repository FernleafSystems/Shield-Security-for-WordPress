<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\DB\Report\Ops as ReportsDB;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1700() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Reports\Select $selector */
		$selector = $mod->getDbHandler_Reports()->getQuerySelector();
		/** @var Reports\EntryVO[] $latest */
		$latest = $selector->setLimit( 20 )
						   ->setOrderBy( 'sent_at' )
						   ->setResultsAsVo( true )
						   ->query();

		foreach ( $latest as $entry ) {
			/** @var ReportsDB\Record $record */
			$record = $mod->getDbHandler_ReportLogs()->getRecord();
			$record->type = $entry->type;
			$record->interval_length = $entry->frequency;
			$record->interval_end_at = $entry->interval_end_at;
			$record->created_at = $entry->created_at;
		}
	}
}