<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Retrieve', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_retrieve.php' );

class ICWP_WPSF_Query_TrafficEntry_Retrieve extends ICWP_WPSF_Query_BaseRetrieve {

	public function __construct() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_TrafficEntryVO.php' );
	}

	/**
	 * @return ICWP_WPSF_TrafficEntryVO[]|stdClass[]
	 */
	public function query() {

		$aData = parent::query();

		if ( $this->isResultsAsVo() ) {
			$aData = array_map(
				function ( $oResult ) {
					return ( new ICWP_WPSF_TrafficEntryVO() )->setRawData( $oResult );
				},
				$aData
			);
		}

		return $aData;
	}
}