<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;

class ZoneReportDB extends BaseZoneReport {

	public function getZoneName() :string {
		return __( 'Database' );
	}

	protected function getLoadLogsWheres() :array {
		// TODO: Implement getLoadLogsWheres() method.
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		// TODO: Implement getUniqFromLog() method.
	}
}