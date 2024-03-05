<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\CrowdSecSignals\Ops;

trait Common {

	public function filterByScope( string $scope ) {
		return $this->addWhereEquals( 'scope', $scope );
	}

	public function filterByValue( string $value ) {
		return $this->addWhereEquals( 'value', $value );
	}
}