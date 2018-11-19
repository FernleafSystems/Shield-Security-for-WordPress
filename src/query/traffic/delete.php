<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Delete', false ) ) {
	return;
}

require_once( __DIR__.'/common.php' );
require_once( dirname( __DIR__ ).'/base/delete.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_TrafficEntry_Delete extends ICWP_WPSF_Query_BaseDelete {

	use ICWP_WPSF_Query_TrafficEntry_Common;

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Select
	 */
	protected function getSelector() {
		require_once( __DIR__.'/select.php' );
		$oCounter = new ICWP_WPSF_Query_TrafficEntry_Select();
		return $oCounter->setTable( $this->getTable() );
	}
}