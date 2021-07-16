<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

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