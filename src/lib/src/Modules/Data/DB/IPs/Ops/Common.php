<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\Ops;

trait Common {

	public function filterByIPHuman( string $ip ) :self {
		return $this->addRawWhere( [ '`ip`', '=', sprintf( "INET6_ATON('%s')", $ip ) ] );
	}
}