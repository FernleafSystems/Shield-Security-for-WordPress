<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Builds snapshots based on the latest complete "full" snapshots + the subsequent diffs.
 */
class RetrieveSnapshot {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function latest() :SnapshotVO {
		return $this->latestPreceding( Services::Request()->carbon( true )->timestamp );
	}

	/**
	 * @throws \Exception
	 */
	public function first() :array {
		/** @var ChangesDB\Select $select */
		$select = $this->mod()
					   ->getChangeTrackCon()
					   ->getDbH_Changes()
					   ->getQuerySelector();
		/** @var ?ChangesDB\Record $full */
		$full = $select->filterIsFull()
					   ->setOrderBy( 'created_at', 'ASC', true )
					   ->first();
		if ( empty( $full ) ) {
			throw new \Exception( 'No full snapshot available' );
		}
		return $full->data;
	}

	/**
	 * @throws \Exception
	 */
	public function fullFromDiffRecord( ChangesDB\Record $record ) :SnapshotVO {
		return $this->latestPreceding( $record->created_at );
	}

	/**
	 * Provides the first available snapshot that was taken after the given timestamp.
	 * @throws \Exception
	 */
	public function latestFollowing( int $timestamp ) :SnapshotVO {
		/** @var ChangesDB\Select $select */
		$select = $this->mod()
					   ->getChangeTrackCon()
					   ->getDbH_Changes()
					   ->getQuerySelector();

		/** @var ?ChangesDB\Record $snapRecord */
		$snapRecord = $select->addWhere( 'created_at', $timestamp, '>=' )
							 ->setOrderBy( 'created_at', 'ASC', true )
							 ->first();
		if ( empty( $snapRecord ) ) {
			throw new \Exception( 'No snapshot available' );
		}

		if ( $snapRecord->is_diff ) {
			/** @var ?ChangesDB\Record $fullRecord */
			$fullRecord = $select->filterIsFull()
								 ->addWhere( 'created_at', $timestamp, '<' )
								 ->setOrderBy( 'created_at', 'DESC', true )
								 ->first();
			if ( empty( $fullRecord ) ) {
				throw new \Exception( 'No full snapshot available - this is inconsistent' );
			}

			$snapshot = Convert::RecordToSnap( $fullRecord );

			/** @var ChangesDB\Record[] $diffs */
			$diffs = $select->filterIsDiff()
							->addWhereOlderThan( $snapRecord->created_at + 1 )
							->addWhereNewerThan( $fullRecord->created_at )
							->setOrderBy( 'created_at', 'ASC', true )
							->queryWithResult();
			$this->applyDiffsToFull(
				$snapshot,
				\array_map(
					function ( $diff ) {
						return Convert::RecordToSnap( $diff );
					},
					\is_array( $diffs ) ? $diffs : []
				)
			);
		}
		else {
			$snapshot = Convert::RecordToSnap( $snapRecord );
		}

		return $snapshot;
	}

	/**
	 * Provides the first available snapshot that was taken before the given timestamp.
	 * @throws \Exception
	 */
	public function latestPreceding( int $timestamp ) :SnapshotVO {
		/** @var ChangesDB\Select $select */
		$select = $this->mod()
					   ->getChangeTrackCon()
					   ->getDbH_Changes()
					   ->getQuerySelector();

		/** @var ?ChangesDB\Record $fullRecord */
		$fullRecord = $select->filterIsFull()
							 ->addWhere( 'created_at', $timestamp, '<=' )
							 ->setOrderBy( 'created_at', 'DESC', true )
							 ->first();
		if ( empty( $fullRecord ) ) {
			throw new \Exception( 'No full snapshot available' );
		}

		$snapshot = new SnapshotVO();
		$snapshot->data = $fullRecord->data;
		$snapshot->is_diff = false;
		$snapshot->snapshot_at = $fullRecord->created_at;

		/** @var ChangesDB\Record[] $diffs */
		$diffs = $select->filterIsDiff()
						->addWhere( 'created_at', $timestamp, '<=' )
						->addWhereNewerThan( $fullRecord->created_at )
						->setOrderBy( 'created_at', 'ASC', true )
						->queryWithResult();

		$this->applyDiffsToFull(
			$snapshot,
			\array_map(
				function ( $diff ) {
					return Convert::RecordToSnap( $diff );
				},
				\is_array( $diffs ) ? $diffs : []
			)
		);

		return $snapshot;
	}

	/**
	 * @param SnapshotVO[] $diffs
	 */
	private function applyDiffsToFull( SnapshotVO $snapshot, array $diffs ) :void {
		foreach ( $diffs as $diff ) {
			( new AddDiffToFull( $snapshot, $diff ) )->run();
		}
	}
}