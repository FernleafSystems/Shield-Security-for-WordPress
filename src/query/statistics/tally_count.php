<?php

if ( class_exists( 'ICWP_WPSF_Query_Tally_Count', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/count.php' );

class ICWP_WPSF_Query_Tally_Count extends ICWP_WPSF_Query_BaseCount {
}