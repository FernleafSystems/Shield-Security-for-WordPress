<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

trait Common {

	public function filterByFrequency( string $freq ) :self {
		return $this->addWhere( 'frequency', $freq );
	}

	public function filterByType( string $type ) :self {
		if ( in_array( $type, [ Handler::TYPE_INFO, Handler::TYPE_ALERT ] ) ) {
			$this->addWhere( 'type', $type );
		}
		return $this;
	}
}