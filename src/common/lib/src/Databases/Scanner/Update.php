<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'ignored_at' => Services::Request()->ts() ) );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotified( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'notified_at' => Services::Request()->ts() ) );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'ignored_at' => 0 ) );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotNotified( $oEntry ) {
		return $this->updateEntry( $oEntry, array( 'notified_at' => 0 ) );
	}
}