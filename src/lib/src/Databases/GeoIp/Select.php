<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use BaseGeoIp;

	/**
	 * @param string $sIp
	 * @return EntryVO
	 */
	public function byIp( $sIp ) {
		return $this->filterByIp( inet_pton( $sIp ) )
					->setResultsAsVo( true )
					->first();
	}

	/**
	 * @return string[]
	 */
	public function getDistinctIps() {
		$aIps = array_filter( array_map(
			function ( $sIp ) {
				return inet_ntop( $sIp );
			},
			$this->getDistinctForColumn( 'ip' )
		) );
		asort( $aIps );
		return $aIps;
	}
}