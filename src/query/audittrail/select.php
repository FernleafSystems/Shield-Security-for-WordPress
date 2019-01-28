<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_AuditTrail_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function filterByContext( $sContext ) {
		return $this;
	}

	/**
	 * @param $sContext
	 * @return int|stdClass[]
	 */
	public function forContext( $sContext ) {
		return $this->query();
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_AuditTrailEntryVO';
	}
}