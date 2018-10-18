<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Count', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/count.php' );

class ICWP_WPSF_Query_AuditTrail_Count extends ICWP_WPSF_Query_BaseCount {

	/**
	 * @param string $sContext
	 * @return int
	 */
	public function forContext( $sContext ) {
		return $this->reset()
					->addWhereEquals( 'context', $sContext )
					->query();
	}
}