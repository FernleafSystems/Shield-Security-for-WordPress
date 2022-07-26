<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Select extends Base\Select {

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