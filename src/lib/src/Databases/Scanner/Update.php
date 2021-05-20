<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param string $scan
	 * @return bool
	 */
	public function clearIgnoredAtForScan( $scan ) {
		return $this->setUpdateWheres( [ 'scan' => $scan ] )
					->setUpdateData( [ 'ignored_at' => 0 ] )
					->query() !== false;
	}

	/**
	 * @param string $scan
	 * @return bool
	 */
	public function clearNotifiedAtForScan( $scan ) {
		return $this->setUpdateWheres( [ 'scan' => $scan ] )
					->setUpdateData( [ 'notified_at' => 0 ] )
					->query() !== false;
	}

	/**
	 * @param string $scan
	 * @return bool
	 */
	public function setAllNotifiedForScan( $scan ) {
		return $this
				   ->setUpdateWheres( [
					   'scan'       => $scan,
					   'ignored_at' => 0,
				   ] )
				   ->setUpdateData( [ 'notified_at' => Services::Request()->ts() ] )
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