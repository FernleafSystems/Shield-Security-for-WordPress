<?php

if ( class_exists( 'ICWP_WPSF_Query_Tally_Delete', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base_delete.php' );

class ICWP_WPSF_Query_Tally_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @return ICWP_WPSF_Query_BaseCount|ICWP_WPSF_Query_Tally_Count
	 */
	protected function getCounter() {
		require_once( __DIR__.'/tally_count.php' );
		$oCounter = new ICWP_WPSF_Query_Tally_Count();
		return $oCounter->setTable( $this->getTable() );
	}
}