<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	public function countAllForEachScan() :array {
		/** @var array[] $res */
		$res = $this->setCustomSelect( '`scan`,COUNT(*) as count' )
					->setGroupBy( 'scan' )
					->setResultsAsVo( false )
					->setSelectResultsFormat( ARRAY_A )
					->query();
		$counts = [];
		if ( is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$counts[ $entry[ 'scan' ] ] = $entry[ 'count' ];
			}
		}
		return $counts;
	}

	public function countUnfinishedForEachScan() :array {
		/** @var array[] $res */
		$res = $this->setCustomSelect( '`scan`,COUNT(*) as count' )
					->filterByNotFinished()
					->setGroupBy( 'scan' )
					->setResultsAsVo( false )
					->setSelectResultsFormat( ARRAY_A )
					->query();
		$counts = [];
		if ( is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$counts[ $entry[ 'scan' ] ] = $entry[ 'count' ];
			}
		}
		return $counts;
	}

	/**
	 * Not quite right. it'll only get the latest finished_at, not the currently processing item
	 */
	public function getCurrentScan() :string {
		return (string)$this->reset()
							->setColumnsToSelect( [ 'scan' ] )
							->setOrderBy( 'finished_at', 'desc' )
							->setLimit( 1 )
							->queryVar();
	}

	/**
	 * @return string[]
	 */
	public function getInitiatedScans() {
		return $this->getDistinctForColumn( 'scan' );
	}

	public function getUnfinishedScans() :array {
		return $this->reset()
					->filterByNotFinished()
					->addColumnToSelect( 'scan' )
					->setIsDistinct( true )
					->query();
	}

	public function countForScan( string $scan ) :int {
		return $this->reset()
					->filterByScan( $scan )
					->count();
	}
}