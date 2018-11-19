<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseInsert {

	/**
	 * Requires IP and List to be set on VO.
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function insert( $oIp ) {
		$bSuccess = false;
		if ( Services::IP()->isValidIpOrRange( $oIp->ip ) && !empty( $oIp->list ) ) {
			$oIp->is_range = strpos( $oIp->getIp(), '/' ) !== false;
			$bSuccess = parent::insert( $oIp );
		}
		return $bSuccess;
	}
}