<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops as SnapshotsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Retrieve {

	use PluginControllerConsumer;

	public function count() :int {
		return self::con()
			->db_con
			->activity_snapshots
			->getQuerySelector()
			->count();
	}

	/**
	 * @return SnapshotsDB\Record[]
	 */
	public function all() :array {
		/** @var SnapshotsDB\Record[] $snaps */
		$snaps = [];
		$selector = self::con()
			->db_con
			->activity_snapshots
			->getQuerySelector()
			->setNoOrderBy();

		$toDelete = [];
		foreach ( $selector->all() as $record ) {
			/** @var SnapshotsDB\Record $record */
			if ( isset( $snaps[ $record->slug ] ) && $snaps[ $record->slug ]->created_at > $record->created_at ) {
				$toDelete[] = $record->id;
				continue;
			}
			$snaps[ $record->slug ] = $record;
		}

		// This shouldn't be necessary, but we build this in here defensively, just in case snapshots start to cumulate
		if ( !empty( $toDelete ) ) {
			self::con()
				->db_con
				->activity_snapshots
				->getQueryDeleter()
				->addWhereIn( 'id', $toDelete )
				->query();
		}

		return $snaps;
	}

	public function latest( string $slug ) :?SnapshotsDB\Record {
		/** @var SnapshotsDB\Select $select */
		$select = self::con()->db_con->activity_snapshots->getQuerySelector();
		/** @var ?SnapshotsDB\Record $record */
		return $select->filterBySlug( $slug )->first();
	}
}