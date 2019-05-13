<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

trait BaseGeoIp {

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $bBinaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByIp( $bBinaryIp ) {
		if ( inet_ntop( $bBinaryIp ) !== false ) {
			$this->addWhereEquals( 'ip', $bBinaryIp );
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