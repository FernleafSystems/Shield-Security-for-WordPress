<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Store {

	use PluginControllerConsumer;

	public function store( SnapshotVO $snapshot ) :bool {
		$dbCon = self::con()->db_con;
		/** @deprecated 19.2 - to be retained for upgrades from 19.0. */
		$dbh = isset( $dbCon->activity_snapshots ) ? $dbCon->activity_snapshots : $dbCon->loadDbH( 'snapshots' );
		return $dbh->getQueryInserter()->insert( Convert::SnapToRecord( $snapshot ) );
	}
}