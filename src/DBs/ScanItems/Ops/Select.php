<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops;

class Select extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select {

	use Common;

	public function countAllForEachScan() :array {
		/** @var Record[] $res */
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
		/** @var Record[] $res */
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
}