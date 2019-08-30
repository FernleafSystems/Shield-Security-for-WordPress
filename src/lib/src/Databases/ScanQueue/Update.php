<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, [ 'ignored_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotified( $oEntry ) {
		return $this->updateEntry( $oEntry, [ 'notified_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotIgnored( $oEntry ) {
		return $this->updateEntry( $oEntry, [ 'ignored_at' => 0 ] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setNotNotified( $oEntry ) {
		return $this->updateEntry( $oEntry, [ 'notified_at' => 0 ] );
	}
}