<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;

class Convert {

	public static function RecordToSnap( ChangesDB\Record $record ) :SnapshotVO {
		$snapshot = new SnapshotVO();
		$snapshot->data = $record->data;
		$snapshot->is_diff = $record->is_diff;
		$snapshot->snapshot_at = $record->created_at;
		return $snapshot;
	}
}