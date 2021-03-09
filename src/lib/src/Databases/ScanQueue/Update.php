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
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function setFinished( $entry ) {
		return $this->updateEntry( $entry, [ 'finished_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function setStarted( $entry ) {
		return $this->updateEntry( $entry, [ 'started_at' => Services::Request()->ts() ] );
	}
}