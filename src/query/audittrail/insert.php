<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Insert', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/insert.php' );

class ICWP_WPSF_Query_AuditTrail_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param ICWP_WPSF_AuditTrailEntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) {
		if ( !isset( $oEntry->ip ) ) {
			$oEntry->ip = $this->loadIpService()->getRequestIp();
		}
		if ( is_array( $oEntry->message ) ) {
			$oEntry->message = implode( ' ', $oEntry->message );
		}
		return parent::insert( $oEntry );
	}
}