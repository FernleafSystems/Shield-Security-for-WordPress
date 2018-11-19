<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseInsert;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends BaseInsert {

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