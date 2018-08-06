<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Create', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_TrafficEntry_Create extends ICWP_WPSF_Query_Base {

	/**
	 * @param ICWP_WPSF_TrafficEntryVO $oEntry
	 * @return bool|int
	 */
	public function create( ICWP_WPSF_TrafficEntryVO $oEntry ) {
		$mResult = $this->loadDbProcessor()
						->insertDataIntoTable( $this->getTable(), $oEntry->getRawDataAsArray() );
		return ( $mResult === false ) ? false : $mResult > 0;
	}
}