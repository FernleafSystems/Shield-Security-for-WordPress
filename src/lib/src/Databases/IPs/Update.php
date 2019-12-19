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
	 * @param EntryVO $oIp
	 * @param int     $nTransCount
	 * @return bool
	 */
	public function updateTransgressions( $oIp, $nTransCount ) {
		return $this->updateEntry(
			$oIp,
			[
				'transgressions' => max( 0, $nTransCount ),
				'last_access_at' => Services::Request()->ts()
			]
		);
	}

	/**
	 * @param EntryVO $oIp
	 * @param string  $sLabel
	 * @return bool
	 */
	public function updateLabel( $oIp, $sLabel ) {
		return $this->updateEntry( $oIp, [ 'label' => trim( $sLabel ) ] );
	}

	/**
	 * Also updates last access at
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function updateLastAccessAt( $oIp ) {
		return $this->updateEntry( $oIp, [ 'last_access_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function setBlocked( $oIp ) {
		return $this->updateEntry( $oIp, [ 'blocked_at' => Services::Request()->ts() ] );
	}
}