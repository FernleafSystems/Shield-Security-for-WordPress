<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Select extends Base\Select {

	use Common;

	public function countForEachScan() :array {
		/** @var array[] $res */
		$res = $this->setCustomSelect( '`scan`,COUNT(*) as count' )
					->setGroupBy( 'scan' )
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