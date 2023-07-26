<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\ZoneReportDatabase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\DiffVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper\SnapDB;

class Database extends Base {

	public function canSnapRealtime() :bool {
		return true;
	}

	/**
	 * @snapshotDiff
	 */
	public function snapshotDiffForTables( DiffVO $diff ) {
		if ( isset( $diff->changed[ 'tables' ] ) ) {
			$old = $diff->changed[ 'tables' ][ 'old' ];
			$new = $diff->changed[ 'tables' ][ 'new' ];

			$added = \array_diff( $new, $old );
			$removed = \array_diff( $old, $new );
			if ( !empty( $added ) ) {
				$this->fireAuditEvent( 'db_tables_added', [
					'tables' => \implode( ', ', $added ),
				] );
			}
			if ( !empty( $removed ) ) {
				$this->fireAuditEvent( 'db_tables_removed', [
					'tables' => \implode( ', ', $removed ),
				] );
			}
		}
	}

	public function getReporter() :ZoneReportDatabase {
		return new ZoneReportDatabase();
	}

	public function getSnapper() :SnapDB {
		return new SnapDB();
	}
}