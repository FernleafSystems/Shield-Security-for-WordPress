<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops;

trait Common {

	public function filterByReportID( string $uniqueID ) :self {
		return $this->addWhere( 'unique_id', $uniqueID );
	}

	public function filterByInterval( string $interval ) :self {
		return $this->addWhere( 'interval_length', $interval );
	}

	public function filterByType( string $type ) {
		return $this->addWhere( 'type', $type );
	}

	public function filterByProtected( bool $isProtected ) {
		return $this->addWhere( 'protected', $isProtected ? 1 : 0 );
	}
}