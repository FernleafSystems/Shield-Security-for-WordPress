<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops;

class Select extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select {

	use Common;

	/**
	 * @return array<int,int>
	 */
	public function countAllForEachScan() :array {
		/** @var Record[]|null $res */
		$res = $this->setCustomSelect( '`scan_ref`,COUNT(*) as count' )
					->setGroupBy( 'scan_ref' )
					->setSelectResultsFormat( ARRAY_A )
					->queryWithResult();
		return $this->rowCountsFromResults( $res );
	}

	/**
	 * @return array<int,int>
	 */
	public function countUnfinishedForEachScan() :array {
		/** @var Record[]|null $res */
		$res = $this->setCustomSelect( '`scan_ref`,COUNT(*) as count' )
					->filterByNotFinished()
					->setGroupBy( 'scan_ref' )
					->setSelectResultsFormat( ARRAY_A )
					->queryWithResult();
		return $this->rowCountsFromResults( $res );
	}

	/**
	 * @return array<int,array{total:int,unfinished:int}>
	 */
	public function countProgressForEachScan() :array {
		/** @var Record[]|null $res */
		$res = $this->setCustomSelect( '`scan_ref`,SUM(CASE WHEN `item_count`>0 THEN `item_count` ELSE 1 END) as count_all,SUM(CASE WHEN `finished_at`=0 THEN CASE WHEN `item_count`>0 THEN `item_count` ELSE 1 END ELSE 0 END) as count_unfinished' )
					->setGroupBy( 'scan_ref' )
					->setSelectResultsFormat( ARRAY_A )
					->queryWithResult();
		$counts = [];
		if ( \is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$entry = $entry->getRawData();
				$counts[ (int)$entry[ 'scan_ref' ] ] = [
					'total'      => (int)$entry[ 'count_all' ],
					'unfinished' => (int)$entry[ 'count_unfinished' ],
				];
			}
		}
		return $counts;
	}

	/**
	 * @param Record[]|null $results
	 * @return array<int,int>
	 */
	private function rowCountsFromResults( ?array $results ) :array {
		$counts = [];
		foreach ( $results ?? [] as $entry ) {
			$row = $entry->getRawData();
			$counts[ (int)$row[ 'scan_ref' ] ] = (int)$row[ 'count' ];
		}
		return $counts;
	}
}
