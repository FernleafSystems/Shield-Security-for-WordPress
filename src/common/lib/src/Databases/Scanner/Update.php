<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * Also updates last access at
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'ignored_at' => Services::Request()->ts() ) );
	}

	/**
	 * Also updates last access at
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'ignored_at' => 0 ) );
	}
}