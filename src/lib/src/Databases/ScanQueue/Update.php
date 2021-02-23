<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function storeResults( $entry ) {
		return isset( $entry->results ) &&
			   $this->updateEntry( $entry, [ 'results' => gzcompress( $entry->getRawData()[ 'results' ] ) ] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setFinished( $oEntry ) {
		return $this->updateEntry( $oEntry, [ 'finished_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function setStarted( $oEntry ) {
		return $this->updateEntry( $oEntry, [ 'started_at' => Services::Request()->ts() ] );
	}
}