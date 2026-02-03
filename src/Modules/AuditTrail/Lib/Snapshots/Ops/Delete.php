<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops as SnapshotDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Delete {

	use PluginControllerConsumer;

	public function delete( string $slug ) :bool {
		$dbh = self::con()->db_con->activity_snapshots;
		if ( empty( $dbh ) ) {
			$dbh = self::con()->db_con->loadDbH( 'snapshots' );
		}
		/** @var SnapshotDB\Delete $deleter */
		$deleter = $dbh->getQueryDeleter();
		/** @deprecated 19.2 - to be retained for upgrades from 19.0. */
		return \method_exists( $deleter, 'filterBySlug' ) ?
			$deleter->filterBySlug( $slug )->query() : $deleter->addWhereEquals( 'slug', $slug )->query();
	}
}