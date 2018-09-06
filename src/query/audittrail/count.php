<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Count', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/count.php' );

class ICWP_WPSF_Query_AuditTrail_Count extends ICWP_WPSF_Query_BaseCount {
}