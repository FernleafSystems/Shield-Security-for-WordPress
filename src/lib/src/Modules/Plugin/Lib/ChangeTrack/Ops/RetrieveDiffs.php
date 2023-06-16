<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

/**
 * Builds snapshots based on the latest complete "full" snapshots + the subsequent diffs.
 */
class RetrieveDiffs {

	use ModConsumer;

	/**
	 * @return SnapshotVO[]
	 * @throws \Exception
	 */
	public function between( int $from, int $until ) :array {
		$allDiffs = [];

		/** @var ChangesDB\Select $select */
		$select = $this->mod()
					   ->getChangeTrackCon()
					   ->getDbH_Changes()
					   ->getQuerySelector();

		$runningSnapshot = ( new RetrieveSnapshot() )->latestFollowing( $from );

		/** @var ChangesDB\Record[] $records */
		$records = $select->addWhereNewerThan( $from )
						  ->addWhereOlderThan( $until )
						  ->setOrderBy( 'created_at', 'ASC', true )
						  ->queryWithResult();

		foreach ( $records as $record ) {
			$snap = Convert::RecordToSnap( $record );
			if ( $snap->is_diff ) {
				$allDiffs[] = $snap;
				( new AddDiffToFull( $runningSnapshot, $snap ) )->run();
			}
			else {
				$allDiffs[] = ( new Diff( $runningSnapshot, $snap ) )->run();
				$runningSnapshot = $snap;
			}
		}

		return $allDiffs;
	}
}