<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

trait Common {

	public function filterByFrequency( string $freq ) :self {
		return $this->addWhere( 'frequency', $freq );
	}

	public function filterByType( string $type ) :self {
		if ( in_array( $type, [ Constants::REPORT_TYPE_INFO, Constants::REPORT_TYPE_ALERT ] ) ) {
			$this->addWhere( 'type', $type );
		}
		return $this;
	}
}