<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

trait Common {

	public function filterByReportID( string $uniqueID ) :self {
		return $this->addWhere( 'unique_id', $uniqueID );
	}

	public function filterByInterval( string $interval ) :self {
		return $this->addWhere( 'interval_length', $interval );
	}

	public function filterByType( string $type ) {
		if ( \in_array( $type, [ Constants::REPORT_TYPE_INFO, Constants::REPORT_TYPE_ALERT ] ) ) {
			$this->addWhere( 'type', $type );
		}
		return $this;
	}
}