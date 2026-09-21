<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};

class QueuedSyncState {

	public static function hasProblem( Record $record ) :bool {
		// A fresh immediate queue request supersedes earlier failures for the current status.
		if ( $record->queue_status === SitesDB::QUEUE_QUEUED
			 && $record->queued_at > 0
			 && $record->next_ping_at <= $record->queued_at
			 && $record->queued_at >= \max( $record->last_ping_failure_at, $record->last_export_failure_at ) ) {
			return false;
		}
		return \in_array( $record->queue_status, [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_IDLE ], true )
			   && ( $record->consecutive_failures > 0 || \max( $record->last_ping_failure_at, $record->last_export_failure_at ) > $record->last_export_success_at );
	}

	public static function sqlHasProblem() :string {
		return \sprintf(
			'(`queue_status` IN (%s,%s) AND NOT (`queue_status`=%s AND `queued_at`>0 AND `next_ping_at`<=`queued_at` AND `queued_at`>=`last_ping_failure_at` AND `queued_at`>=`last_export_failure_at`) AND (`consecutive_failures`>0 OR `last_ping_failure_at`>`last_export_success_at` OR `last_export_failure_at`>`last_export_success_at`))',
			"'".SitesDB::QUEUE_QUEUED."'",
			"'".SitesDB::QUEUE_IDLE."'",
			"'".SitesDB::QUEUE_QUEUED."'"
		);
	}
}
