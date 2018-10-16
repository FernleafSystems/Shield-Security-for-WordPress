<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Update', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/update.php' );

class ICWP_WPSF_Query_Ips_Update extends ICWP_WPSF_Query_BaseUpdate {

	/**
	 * Also updates last access at
	 * @param ICWP_WPSF_IpsEntryVO $oIp
	 * @return bool
	 */
	public function incrementTransgressions( $oIp ) {
		return $this->updateIp(
			$oIp,
			array(
				'transgressions' => $oIp->getTransgressions() + 1,
				'last_access_at' => $this->loadDP()->time()
			)
		);
	}

	/**
	 * @param ICWP_WPSF_IpsEntryVO $oIp
	 * @param string               $sLabel
	 * @return bool
	 */
	public function updateLabel( $oIp, $sLabel ) {
		return $this->updateIp( $oIp, array( 'label' => $sLabel ) );
	}

	/**
	 * Also updates last access at
	 * @param ICWP_WPSF_IpsEntryVO $oIp
	 * @return bool
	 */
	public function updateLastAccessAt( $oIp ) {
		return $this->updateIp(
			$oIp,
			array( 'last_access_at' => $this->loadDP()->time() )
		);
	}

	/**
	 * @param ICWP_WPSF_IpsEntryVO $oIp
	 * @param array                $aUpdateData
	 * @return bool
	 */
	public function updateIp( $oIp, $aUpdateData = array() ) {

		$bSuccess = false;
		if ( !empty( $aUpdateData ) && $oIp instanceof ICWP_WPSF_IpsEntryVO ) {

			$mResult = $this
				->setUpdateWheres( array( 'id' => $oIp->getId() ) )
				->setUpdateData( $aUpdateData )
				->query();
			$bSuccess = is_numeric( $mResult ) && $mResult === 1;

			if ( $bSuccess ) {
				foreach ( $aUpdateData as $sColumn => $mValue ) {
					$oIp->{$sColumn} = $mValue;
				}
			}
		}

		return $bSuccess;
	}
}