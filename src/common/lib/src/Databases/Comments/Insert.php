<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @param EntryVO $oToken
	 * @return bool
	 */
	public function insert( $oToken ) {
		if ( !isset( $oToken->ip ) ) {
			$oToken->ip = Services::IP()->getRequestIp();
		}
		return parent::insert( $oToken );
	}
}