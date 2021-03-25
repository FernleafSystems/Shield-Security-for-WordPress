<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

class Select extends Base\Select {

	use BaseGeoIp;
	use Base\Traits\Select_IPTable;

	/**
	 * @param string $sIp
	 * @return EntryVO
	 */
	public function byIp( $sIp ) {
		return $this->filterByIp( inet_pton( $sIp ) )
					->setResultsAsVo( true )
					->first();
	}
}