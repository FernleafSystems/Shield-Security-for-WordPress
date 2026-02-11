<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Store {

	use PluginControllerConsumer;

	public function store( SnapshotVO $snapshot ) :bool {
		return self::con()
			->db_con
			->activity_snapshots
			->getQueryInserter()
			->insert( Convert::SnapToRecord( $snapshot ) );
	}
}
