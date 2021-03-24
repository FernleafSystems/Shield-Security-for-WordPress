<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * Also updates last access at
	 * @param int     $nIncrement
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function incrementTransgressions( $oIp, $nIncrement = 1 ) {
		return $this->updateTransgressions( $oIp, $oIp->transgressions + $nIncrement );
	}

	/**
	 * @param EntryVO $IP
	 * @param int     $offenseCount
	 * @return bool
	 */
	public function updateTransgressions( $IP, $offenseCount ) {
		return $this->updateEntry( $IP, [
			'transgressions' => max( 0, $offenseCount ),
			'last_access_at' => Services::Request()->ts()
		] );
	}

	/**
	 * @param EntryVO $IP
	 * @param string  $label
	 * @return bool
	 */
	public function updateLabel( $IP, $label ) {
		return $this->updateEntry( $IP, [ 'label' => trim( $label ) ] );
	}

	/**
	 * Also updates last access at
	 * @param EntryVO $IP
	 * @return bool
	 */
	public function updateLastAccessAt( $IP ) {
		return $this->updateEntry( $IP, [ 'last_access_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $IP
	 * @return bool
	 */
	public function setBlocked( $IP ) {
		return $this->updateEntry( $IP, [ 'blocked_at' => Services::Request()->ts() ] );
	}
}