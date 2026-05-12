<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Services\Services;

trait Common {

	public function filterByIPHuman( string $ip ) :self {
		$normalisedIp = null;
		$rightSide = null;
		if ( empty( $ip ) ) {
			$rightSide = "''";
		}
		elseif ( Services::IP()->isValidIp( $ip ) ) {
			$normalisedIp = $ip;
		}
		else {
			$packedLength = \strlen( $ip );
			if ( \function_exists( 'inet_ntop' ) && \in_array( $packedLength, [ 4, 16 ], true ) ) {
				$maybeHumanIp = \inet_ntop( $ip );
				if ( \is_string( $maybeHumanIp ) && Services::IP()->isValidIp( $maybeHumanIp ) ) {
					$normalisedIp = $maybeHumanIp;
				}
			}
		}

		if ( $normalisedIp !== null ) {
			$rightSide = IpAddressSql::literalFromIp( $normalisedIp );
		}

		if ( $rightSide !== null ) {
			$this->addRawWhere( [ '`ip`', '=', $rightSide ] );
		}

		return $this;
	}
}
