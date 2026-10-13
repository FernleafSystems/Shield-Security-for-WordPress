<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};

class ExportWaitState {

	public static function hasQualifyingSuccess( Record $row ) :bool {
		return $row->last_export_success_at > 0
			   && $row->last_export_success_at >= $row->last_ping_success_at;
	}

	public static function isExpired( Record $row, int $now ) :bool {
		return self::isActiveWait( $row )
			   && $row->expected_export_by > 0
			   && $row->expected_export_by <= $now
			   && !self::hasQualifyingSuccess( $row );
	}

	public static function isCooldownBypassed( Record $row, int $now ) :bool {
		return self::isActiveWait( $row )
			   && $row->expected_export_by > $now
			   && !self::hasQualifyingSuccess( $row );
	}

	public static function isReconcilable( Record $row ) :bool {
		return self::isActiveWait( $row ) && self::hasQualifyingSuccess( $row );
	}

	public static function sqlQualifyingSuccess() :string {
		return '(`last_export_success_at`>0 AND `last_export_success_at`>=`last_ping_success_at`)';
	}

	public static function sqlUnsatisfiedSuccess() :string {
		return '(`last_export_success_at`=0 OR `last_export_success_at`<`last_ping_success_at`)';
	}

	private static function isActiveWait( Record $row ) :bool {
		return $row->status === SitesDB::STATUS_ACTIVE
			   && $row->queue_status === SitesDB::QUEUE_WAITING_EXPORT;
	}
}
