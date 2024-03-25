<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;

class ZoneReportDatabase extends BaseZoneReport {

	public function getZoneName() :string {
		return __( 'Database' );
	}

	protected function getLoadLogsWheres() :array {
		return [
			sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", [
				'db_tables_added',
				'db_tables_removed',
			] ) ),
		];
	}

	protected function getNameForLog( LogRecord $log ) :string {
		switch ( $log->event_slug ) {
			case 'db_tables_added':
				$name = __( 'Added Tables', 'wp-simple-firewall' );
				break;
			case 'db_tables_removed':
				$name = __( 'Deleted Tables', 'wp-simple-firewall' );
				break;
			default:
				$name = parent::getNameForLog( $log );
				break;
		}
		return $name;
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return 'db-'.$log->event_slug;
	}
}