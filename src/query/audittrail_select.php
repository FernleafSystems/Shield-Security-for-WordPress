<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Select', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_select.php' );

class ICWP_WPSF_Query_AuditTrail_Select extends ICWP_WPSF_Query_BaseSelect {

	protected function customInit() {
		require_once( __DIR__.'/ICWP_WPSF_AuditTrailEntryVO.php' );
	}

	/**
	 * @return ICWP_WPSF_AuditTrailEntryVO[]|stdClass[]
	 */
	public function query() {

		$aData = parent::query();

		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oAudit ) {
				$aData[ $nKey ] = new ICWP_WPSF_AuditTrailEntryVO( $oAudit );
			}
		}

		return $aData;
	}
}