<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Snapshots\Ops as SnapshotsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class Retrieve {

	use ModConsumer;

	public function count() :int {
		return $this->mod()
					->getDbH_Snapshots()
					->getQuerySelector()
					->count();
	}

	/**
	 * @return SnapshotsDB\Record[]
	 */
	public function all() :array {
		$diffs = [];
		$selector = $this->mod()
						 ->getDbH_Snapshots()
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
		$select = $this->mod()->getDbH_Snapshots()->getQuerySelector();
		/** @var ?SnapshotsDB\Record $record */
		return $select->filterBySlug( $slug )->first();
	}
}