<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems\Ops;

trait Common {

	public function filterByScan( int $scanID ) {
		return $this->addWhereEquals( 'scan_ref', $scanID );
	}
}