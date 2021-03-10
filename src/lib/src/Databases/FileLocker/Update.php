<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function markNotified( EntryVO $entry ) {
		return $this->updateEntry( $entry, [
			'notified_at' => Services::Request()->ts()
		] );
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function markProblem( EntryVO $entry ) {
		return $this->updateEntry( $entry, [
			'detected_at' => Services::Request()->ts(),
			'notified_at' => 0
		] );
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function markReverted( EntryVO $entry ) {
		return $this->updateEntry( $entry, [
			'reverted_at' => Services::Request()->ts()
		] );
	}

	/**
	 * @param EntryVO $entry
	 * @param string  $hash
	 * @return bool
	 */
	public function updateCurrentHash( EntryVO $entry, $hash = '' ) {
		return $this->updateEntry( $entry, [
			'hash_current' => $hash,
			'detected_at'  => empty( $hash ) ? 0 : Services::Request()->ts(),
			'notified_at'  => 0,
		] );
	}
}