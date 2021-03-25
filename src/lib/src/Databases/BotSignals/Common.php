<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

trait Common {

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $binaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByIP( $binaryIp ) {
		if ( inet_ntop( $binaryIp ) !== false ) {
			$this->addWhereEquals( 'ip', $binaryIp );
		}
		return $this;
	}

	public function filterByIPHuman( string $ip ) {
		return $this->filterByIP( inet_pton( $ip ) );
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