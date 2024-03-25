<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops as SnapshotsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\SnapshotVO;

class Convert {

	public static function RecordToSnap( SnapshotsDB\Record $record ) :SnapshotVO {
		$snapshot = new SnapshotVO();
		$snapshot->data = $record->data;
		$snapshot->slug = $record->slug;
		$snapshot->snapshot_at = $record->created_at;
		return $snapshot;
	}

	public static function SnapToRecord( SnapshotVO $snap ) :SnapshotsDB\Record {
		$record = new SnapshotsDB\Record();
		$record->slug = $snap->slug;
		$record->data = $snap->data;
		$record->created_at = $snap->snapshot_at;
		return $record;
	}
}