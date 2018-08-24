<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Insert', false ) ) {
	return;
}

require_once( __DIR__.'/common.php' );
require_once( dirname( __DIR__ ).'/base_insert.php' );

class ICWP_WPSF_Query_TrafficEntry_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param ICWP_WPSF_TrafficEntryVO $oEntry
	 * @return bool
	 */
	public function create( $oEntry ) {
		if ( $oEntry->created_at < 1 ) {
			$oEntry->created_at = $this->loadDP()->time();
		}
		return $this->setInsertData( $oEntry->getRawDataAsArray() )->query() === 1;
	}
}