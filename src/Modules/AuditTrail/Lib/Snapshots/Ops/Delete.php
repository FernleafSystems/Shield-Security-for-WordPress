<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops as SnapshotDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Delete {

	use PluginControllerConsumer;

	public function delete( string $slug ) :bool {
		/** @var SnapshotDB\Delete $deleter */
		$deleter = self::con()->db_con->activity_snapshots->getQueryDeleter();
		return $deleter->filterBySlug( $slug )->query();
	}
}
