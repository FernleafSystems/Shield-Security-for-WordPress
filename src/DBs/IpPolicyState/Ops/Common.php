<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpPolicyState\Ops;

trait Common {

	public function filterByIPRef( int $ipRef ) {
		return $this->addWhereEquals( 'ip_ref', $ipRef );
	}

	public function filterByRiskBand( string $riskBand ) :self {
		return $this->addWhereEquals( 'risk_band', $riskBand );
	}
}
