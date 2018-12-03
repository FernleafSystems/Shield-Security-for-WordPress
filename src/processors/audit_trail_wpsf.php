<?php

if ( class_exists( 'ICWP_WPSF_Processor_AuditTrail_Wpsf' ) ) {
	return;
}

require_once( __DIR__.'/audit_trail_auditor_base.php' );

class ICWP_WPSF_Processor_AuditTrail_Wpsf extends ICWP_WPSF_AuditTrail_Auditor_Base {

	/**
	 */
	public function run() { }
}