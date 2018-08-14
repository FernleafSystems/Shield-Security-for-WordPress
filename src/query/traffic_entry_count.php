<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Count', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

class ICWP_WPSF_Query_TrafficEntry_Count extends ICWP_WPSF_Query_BaseCount {
}