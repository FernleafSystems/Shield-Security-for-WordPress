<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function markProblem( EntryVO $oEntry ) {
		return $this->updateEntry( $oEntry, [
			'detected_at' => Services::Request()->ts()
		] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function markReverted( EntryVO $oEntry ) {
		return $this->updateEntry( $oEntry, [
			'reverted_at' => Services::Request()->ts()
		] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @param string  $sHash
	 * @return bool
	 */
	public function updateCurrentHash( EntryVO $oEntry, $sHash = '' ) {
		return $this->updateEntry( $oEntry, [
			'hash_current' => $sHash,
			'detected_at'  => empty( $sHash ) ? 0 : Services::Request()->ts()
		] );
	}

	/**
	 * @param EntryVO $oEntry
	 * @param string  $sHash
	 * @return bool
	 */
	public function updateOriginalHash( EntryVO $oEntry, $sHash ) {
		return $this->updateEntry( $oEntry, [
			'hash_original' => $sHash,
			'detected_at'   => 0
		] );
	}
}