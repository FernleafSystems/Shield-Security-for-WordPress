<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecSignals\Ops;

/**
 * @deprecated 19.1
 */
trait Common {

	public function filterByScope( string $scope ) {
		return $this->addWhereEquals( 'scope', $scope );
	}

	public function filterByValue( string $value ) {
		return $this->addWhereEquals( 'value', $value );
	}
}