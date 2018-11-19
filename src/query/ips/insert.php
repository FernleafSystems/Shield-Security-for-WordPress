<?php

if ( class_exists( 'ICWP_WPSF_Query_Ips_Insert', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/insert.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_Ips_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * Requires IP and List to be set on VO.
	 * @param ICWP_WPSF_IpsEntryVO $oIp
	 * @return bool
	 */
	public function insert( $oIp ) {
		$bSuccess = false;
		if ( $this->loadIpService()->isValidIpOrRange( $oIp->ip ) && !empty( $oIp->list ) ) {
			$oIp->is_range = strpos( $oIp->getIp(), '/' ) !== false;
			$bSuccess = parent::insert( $oIp );
		}
		return $bSuccess;
	}
}