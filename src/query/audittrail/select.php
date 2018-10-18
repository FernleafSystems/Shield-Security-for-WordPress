<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_AuditTrail_Select extends ICWP_WPSF_Query_BaseSelect {

	protected function customInit() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_AuditTrailEntryVO.php' );
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function filterByContext( $sContext ) {
		return $this->addWhereEquals( 'context', $sContext );
	}

	/**
	 * @param string $sContext
	 * @return ICWP_WPSF_AuditTrailEntryVO[]|stdClass[]
	 */
	public function forContext( $sContext ) {
		return $this->reset()
					->addWhereEquals( 'context', $sContext )
					->query();
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