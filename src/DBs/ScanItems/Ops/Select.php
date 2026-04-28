<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops;

class Select extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select {

	use Common;

	public function countAllForEachScan() :array {
		/** @var ?Record[] $res */
		$res = $this->setCustomSelect( '`scan_ref`,COUNT(*) as count' )
					->setGroupBy( 'scan_ref' )
					->setSelectResultsFormat( ARRAY_A )
					->queryWithResult();
		$counts = [];
		if ( \is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$entry = $entry->getRawData();
				$counts[ $entry[ 'scan_ref' ] ] = $entry[ 'count' ];
			}
		}
		return $counts;
	}

	public function countUnfinishedForEachScan() :array {
		/** @var ?Record[] $res */
		$res = $this->setCustomSelect( '`scan_ref`,COUNT(*) as count' )
					->filterByNotFinished()
					->setGroupBy( 'scan_ref' )
					->setSelectResultsFormat( ARRAY_A )
					->queryWithResult();
		$counts = [];
		if ( \is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$entry = $entry->getRawData();
				$counts[ $entry[ 'scan_ref' ] ] = $entry[ 'count' ];
			}
		}
		return $counts;
	}

	/**
	 * @return array<int|string,array{total:int,unfinished:int}>
	 */
	public function countProgressForEachScan() :array {
		/** @var ?Record[] $res */
		$res = $this->setCustomSelect( '`scan_ref`,COUNT(*) as count_all,SUM(CASE WHEN `finished_at`=0 THEN 1 ELSE 0 END) as count_unfinished' )
					->setGroupBy( 'scan_ref' )
					->setSelectResultsFormat( ARRAY_A )
					->queryWithResult();
		$counts = [];
		if ( \is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$entry = $entry->getRawData();
				$counts[ $entry[ 'scan_ref' ] ] = [
					'total'      => (int)$entry[ 'count_all' ],
					'unfinished' => (int)$entry[ 'count_unfinished' ],
				];
			}
		}
		return $counts;
	}
}
