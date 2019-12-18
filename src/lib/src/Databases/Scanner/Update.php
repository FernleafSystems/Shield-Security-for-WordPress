<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param string $sScan
	 * @return bool
	 */
	public function clearIgnoredAtForScan( $sScan ) {
		return $this->setUpdateWheres( [ 'scan' => $sScan ] )
					->setUpdateData( [ 'ignored_at' => 0 ] )
					->query() !== false;
	}

	/**
	 * @param string $sScan
	 * @return bool
	 */
	public function clearNotifiedAtForScan( $sScan ) {
		return $this->setUpdateWheres( [ 'scan' => $sScan ] )
					->setUpdateData( [ 'notified_at' => 0 ] )
					->query() !== false;
	}

	/**
	 * @param string $sScan
	 * @return bool
	 */
	public function setAllNotifiedForScan( $sScan ) {
		return $this
				   ->setUpdateWheres(
					   [
						   'scan'        => $sScan,
						   'ignored_at'  => 0,
						   'notified_at' => 0,
					   ]
				   )
				   ->setUpdateData(
					   [ 'notified_at' => Services::Request()->ts() ]
				   )
				   ->query() !== false;
	}

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