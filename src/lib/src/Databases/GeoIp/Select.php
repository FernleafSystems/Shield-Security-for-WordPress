<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

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
		return IpListSort::Sort( array_map(
			function ( $sIp ) {
				return inet_ntop( $sIp );
			},
			$this->getDistinctForColumn( 'ip' )
		) );
	}
}