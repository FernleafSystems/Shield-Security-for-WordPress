<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Insert', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/insert.php' );

class ICWP_WPSF_Query_Ips_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * Requires IP and List to be set on VO.
	 * @param ICWP_WPSF_IpsEntryVO $oIp
	 * @return bool
	 */
	public function insert( $oIp ) {

		$bSuccess = false;
		if ( $this->loadIpService()->isValidIpOrRange( $oIp->ip ) && !empty( $oIp->list ) ) {
			$oDP = $this->loadDP();
			$oIp->is_range = strpos( $oIp->getIp(), '/' ) !== false;

			$aData = array_merge(
				array(
					'created_at' => $oDP->time(),
				),
				$oDP->convertStdClassToArray( $oIp->getRowData() )
			);
			$bSuccess = $this->setInsertData( $aData )->query() === 1;
		}
		return $bSuccess;
	}
}