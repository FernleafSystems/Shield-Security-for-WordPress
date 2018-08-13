<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Create', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_insert.php' );

class ICWP_WPSF_Query_TrafficEntry_Create extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param ICWP_WPSF_TrafficEntryVO $oEntry
	 * @return bool
	 */
	public function create( ICWP_WPSF_TrafficEntryVO $oEntry ) {
		if ( $oEntry->created_at < 1 ) {
			$oEntry->created_at = $this->loadDP()->time();
		}
		return $this->setInsertData( $oEntry->getRawDataAsArray() )
					->query();
	}
}