<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * Requires IP and List to be set on VO.
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) {
		if ( !isset( $oEntry->ip ) ) {
			$oEntry->ip = Services::IP()->getRequestIp();
		}
		if ( is_array( $oEntry->message ) ) {
			$oEntry->message = implode( ' ', $oEntry->message );
		}
		return parent::insert( $oEntry );
	}
}