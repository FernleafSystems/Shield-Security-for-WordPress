<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Snapshots\Ops as SnapshotDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class Delete {

	use ModConsumer;

	public function delete( string $slug ) :bool {
		$con = self::con();
		/** @var SnapshotDB\Delete $deleter */
		$deleter = ( \method_exists( $con->db_con, 'dbhSnapshots' ) ?
			$con->db_con->dbhSnapshots() : $con->getModule_AuditTrail()->getDbH_Snapshots() )->getQueryDeleter();
		return $deleter->filterBySlug( $slug )->query();
	}
}