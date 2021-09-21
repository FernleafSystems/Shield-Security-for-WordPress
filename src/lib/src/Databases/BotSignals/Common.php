<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Services\Services;

trait Common {

	/**
	 * COPIED FROM PLUGIN CORE
	 * @param string $ip
	 * @return $this
	 */
	public function filterByIPHuman( string $ip ) {
		$rightSide = null;
		if ( empty( $ip ) ) {
			$rightSide = "''";
		}
		elseif ( Services::IP()->isValidIp( $ip ) || Services::IP()->isValidIp( inet_ntop( $ip ) ) ) {
			$rightSide = sprintf( "INET6_ATON('%s')", Services::IP()->isValidIp( $ip ) ? $ip : inet_ntop( $ip ) );
		}

		if ( !empty( $rightSide ) ) {
			$this->addRawWhere( [ 'ip', '=', $rightSide ] );
		}
		return $this;
	}

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $bBinaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByNotIp( $bBinaryIp ) {
		if ( inet_ntop( $bBinaryIp ) !== false ) {
			$this->addWhere( 'ip', $bBinaryIp, '!=' );
		}
		return $this;
	}
}