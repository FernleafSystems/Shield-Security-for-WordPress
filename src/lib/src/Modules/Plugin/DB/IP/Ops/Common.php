<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\IP\Ops;

trait Common {

	public function filterByIP( string $ip ) {
		return $this->addWhereEquals( 'ip', $ip );
	}

	public function filterByIPHuman( string $ip ) :self {
		return $this->filterByIP( inet_pton( $ip ) );
	}
}