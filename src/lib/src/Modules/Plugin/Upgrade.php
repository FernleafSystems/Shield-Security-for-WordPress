<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports as LegacyReportsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1700() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$legacyReportingMod = $this->getCon()->getModule_Reporting();

		// migrate options.
		foreach ( [ 'frequency_alert', 'frequency_info' ] as $optKey ) {
			$mod->getOptions()->setOpt( $optKey, $legacyReportingMod->getOptions()->getOpt( $optKey ) );
		}

		// move over historical reports.
		/** @var LegacyReportsDB\Select $selector */
		$legacyDBH = $legacyReportingMod->getDbHandler_Reports();
		$selector = $legacyDBH->getQuerySelector();
		/** @var LegacyReportsDB\EntryVO[] $latest */
		$latest = $selector->setLimit( 20 )
						   ->setOrderBy( 'sent_at' )
						   ->setResultsAsVo( true )
						   ->query();

		foreach ( $latest as $entry ) {
			/** @var Db\Report\Ops\Record $record */
			$record = $mod->getDbH_ReportLogs()->getRecord();
			$record->type = $entry->type;
			$record->interval_length = $entry->frequency;
			$record->interval_end_at = $entry->interval_end_at;
			$record->created_at = $entry->sent_at;
		}

		// remove the legacy table.
		$legacyDBH->tableDelete();
	}

	protected function upgrade_1610() {
		// remove old tables
		$WPDB = Services::WpDb();
		foreach (
			[
				'geoip',
				'reporting',
				'spambot_comments_filter',
				'statistics',
				'ip_lists',
				'sessions'
			] as $table
		) {
			$table = sprintf( '%s%s%s', $WPDB->getPrefix(), $this->getCon()->getOptionStoragePrefix(), $table );
			if ( $WPDB->tableExists( $table ) ) {
				$WPDB->doDropTable( $table );
			}
		}
		$WPDB->clearResultShowTables();
	}
}