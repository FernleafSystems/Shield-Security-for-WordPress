<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Delete', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/delete.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_AuditTrail_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_AuditTrail_Select
	 */
	protected function getSelector() {
		require_once( dirname( __FILE__ ).'/select.php' );
		$oCounter = new ICWP_WPSF_Query_AuditTrail_Select();
		return $oCounter->setTable( $this->getTable() );
	}
}