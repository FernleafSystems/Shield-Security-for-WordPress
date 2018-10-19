<?php

if ( class_exists( 'ICWP_WPSF_Query_Tally_Delete', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/delete.php' );

class ICWP_WPSF_Query_Tally_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_Tally_Select
	 */
	protected function getSelector() {
		require_once( __DIR__.'/tally_select.php' );
		$oCounter = new ICWP_WPSF_Query_Tally_Select();
		return $oCounter->setTable( $this->getTable() );
	}
}