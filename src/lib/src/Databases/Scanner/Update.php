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
						   'scan'       => $sScan,
						   'ignored_at' => 0,
					   ]
				   )
				   ->setUpdateData(
					   [ 'notified_at' => Services::Request()->ts() ]
				   )
				   ->query() !== false;
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function setIgnored( $entry ) {
		return $this->updateEntry( $entry, [ 'ignored_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function setNotified( $entry ) {
		return $this->updateEntry( $entry, [ 'notified_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function setNotIgnored( $entry ) {
		return $this->updateEntry( $entry, [ 'ignored_at' => 0 ] );
	}

	/**
	 * @param EntryVO $entry
	 * @return bool
	 */
	public function setNotNotified( $entry ) {
		return $this->updateEntry( $entry, [ 'notified_at' => 0 ] );
	}
}