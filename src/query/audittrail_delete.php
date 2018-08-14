<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Delete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_delete.php' );

class ICWP_WPSF_Query_AuditTrail_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_AuditTrail_Count
	 */
	protected function getCounter() {
		require_once( dirname( __FILE__ ).'/audittrail_count.php' );
		$oCounter = new ICWP_WPSF_Query_AuditTrail_Count();
		return $oCounter->setTable( $this->getTable() );
	}
}