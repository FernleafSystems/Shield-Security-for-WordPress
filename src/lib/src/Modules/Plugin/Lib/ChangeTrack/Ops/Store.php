<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class Store {

	use ModConsumer;

	public function store( SnapshotVO $snapshot ) :bool {
		/** @var ChangesDB\Insert $insert */
		$insert = $this->mod()
					   ->getChangeTrackCon()
					   ->getDbH_Changes()
					   ->getQueryInserter();
		$record = new ChangesDB\Record();
		$record->data = $snapshot->data;
		$record->is_diff = $snapshot->is_diff;
		$record->created_at = $snapshot->snapshot_at;
		return $insert->insert( $record );
	}
}