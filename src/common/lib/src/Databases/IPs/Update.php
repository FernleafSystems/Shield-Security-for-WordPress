<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseUpdate;
use FernleafSystems\Wordpress\Services\Services;

class Update extends BaseUpdate {

	/**
	 * Also updates last access at
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function incrementTransgressions( $oIp ) {
		return $this->updateIp(
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
		return $this->updateIp( $oIp, array( 'label' => trim( $sLabel ) ) );
	}

	/**
	 * Also updates last access at
	 * @param EntryVO $oIp
	 * @return bool
	 */
	public function updateLastAccessAt( $oIp ) {
		return $this->updateIp(
			$oIp,
			array( 'last_access_at' => Services::Request()->ts() )
		);
	}

	/**
	 * @param EntryVO $oIp
	 * @param array   $aUpdateData
	 * @return bool
	 */
	public function updateIp( $oIp, $aUpdateData = array() ) {
		return parent::updateEntry( $oIp, $aUpdateData );
	}
}