<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults\Ops;

trait Common {

	public function filterByScan( int $scanID ) {
		return $this->addWhereEquals( 'scan_ref', $scanID );
	}

	public function filterByResultItems( int $resultItemID ) {
		return $this->addWhereEquals( 'resultitem_ref', $resultItemID );
	}
}