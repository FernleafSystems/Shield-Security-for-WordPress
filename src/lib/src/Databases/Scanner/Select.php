<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	public function countForEachScan() :array {
		/** @var array[] $res */
		$res = $this->setCustomSelect( '`scan`,COUNT(*) as count' )
					->setGroupBy( 'scan' )
					->setResultsAsVo( false )
					->setSelectResultsFormat( ARRAY_A )
					->filterByNotIgnored()
					->query();
		$counts = [];
		if ( is_array( $res ) ) {
			foreach ( $res as $entry ) {
				$counts[ $entry[ 'scan' ] ] = $entry[ 'count' ];
			}
		}
		return $counts;
	}
}