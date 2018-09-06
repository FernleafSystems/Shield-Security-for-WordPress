<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Count', false ) ) {
	return;
}

require_once( __DIR__.'/common.php' );
require_once( dirname( __DIR__ ).'/base/count.php' );

class ICWP_WPSF_Query_TrafficEntry_Count extends ICWP_WPSF_Query_BaseCount {

	use ICWP_WPSF_Query_TrafficEntry_Common;
}