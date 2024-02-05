<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class Store {

	use ModConsumer;

	public function store( SnapshotVO $snapshot ) :bool {
		return self::con()
			->db_con
			->dbhSnapshots()
			->getQueryInserter()
			->insert( Convert::SnapToRecord( $snapshot ) );
	}
}