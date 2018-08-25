<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Select', false ) ) {
	return;
}

require_once( __DIR__.'/common.php' );
require_once( dirname( __DIR__ ).'/base/select.php' );

class ICWP_WPSF_Query_TrafficEntry_Select extends ICWP_WPSF_Query_BaseSelect {

	use ICWP_WPSF_Query_TrafficEntry_Common;

	protected function customInit() {
		require_once( __DIR__.'/ICWP_WPSF_TrafficEntryVO.php' );
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