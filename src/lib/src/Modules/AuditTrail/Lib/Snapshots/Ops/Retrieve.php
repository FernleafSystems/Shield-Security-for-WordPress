<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops as SnapshotsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class Retrieve {

	use ModConsumer;

	public function count() :int {
		return self::con()
			->db_con
			->dbhSnapshots()
			->getQuerySelector()
			->count();
	}

	/**
	 * @return SnapshotsDB\Record[]
	 */
	public function all() :array {
		$diffs = [];
		$selector = self::con()
			->db_con
			->dbhSnapshots()
			->getQuerySelector()
			->setNoOrderBy();
		foreach ( $selector->all() as $record ) {
			/** @var SnapshotsDB\Record $record */
			$diffs[ $record->slug ] = $record;
		}
		return $diffs;
	}

	public function latest( string $slug ) :?SnapshotsDB\Record {
		/** @var SnapshotsDB\Select $select */
		$select = self::con()->db_con->dbhSnapshots()->getQuerySelector();
		/** @var ?SnapshotsDB\Record $record */
		$record = $select->filterBySlug( $slug )->first();
		if ( !\is_a( $record, '\FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops\Record' ) ) {
			$record = null;
		}
		return $record;
	}
}