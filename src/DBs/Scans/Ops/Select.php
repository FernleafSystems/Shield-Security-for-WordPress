<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops;

class Select extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select {

	use Common;

	/**
	 * @return Record|null
	 */
	public function getLatestForScan( string $scan ) {
		return $this->filterByScan( $scan )
					->setOrderBy( 'id', 'DESC', true )
					->first();
	}
}