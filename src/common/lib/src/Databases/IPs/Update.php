<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * Also updates last access at
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function incrementTransgressions( $oIp ) {
		return $this->updateEntry(
			$oIp,
			array(
				'transgressions' => $oIp->getTransgressions() + 1,
				'last_access_at' => Services::Request()->ts()
			)
		);
	}

	/**
	 * @param EntryVO $oIp
	 * @param string  $sLabel
	 * @return bool
	 */
	public function updateLabel( $oIp, $sLabel ) {
		return $this->updateEntry( $oIp, array( 'label' => trim( $sLabel ) ) );
	}

	/**
	 * Also updates last access at
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function updateLastAccessAt( $oIp ) {
		return $this->updateEntry( $oIp, array( 'last_access_at' => Services::Request()->ts() ) );
	}
}