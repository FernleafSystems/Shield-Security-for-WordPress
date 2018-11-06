<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_AuditTrail_Select extends ICWP_WPSF_Query_BaseSelect {

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
	 * @return int|stdClass[]|ICWP_WPSF_AuditTrailEntryVO[]
	 */
	public function query() {
		return parent::query();
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_AuditTrailEntryVO';
	}
}