<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_AuditTrail_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_AuditTrail_Select
	 */
	protected function getSelector() {
		return ( new ICWP_WPSF_Query_AuditTrail_Select() )->setTable( $this->getTable() );
	}
}