<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_TrafficEntry_Delete extends ICWP_WPSF_Query_BaseDelete {

	use ICWP_WPSF_Query_TrafficEntry_Common;

	/**
	 * @return ICWP_WPSF_Query_TrafficEntry_Select
	 */
	protected function getSelector() {
		return ( new ICWP_WPSF_Query_TrafficEntry_Select() )->setTable( $this->getTable() );
	}
}