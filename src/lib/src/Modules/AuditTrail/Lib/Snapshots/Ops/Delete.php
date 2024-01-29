<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Snapshots\Ops as SnapshotDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class Delete {

	use ModConsumer;

	public function delete( string $slug ) :bool {
		/** @var SnapshotDB\Delete $deleter */
		$deleter = self::con()->db_con->dbhSnapshots()->getQueryDeleter();
		return $deleter->filterBySlug( $slug )->query();
	}
}