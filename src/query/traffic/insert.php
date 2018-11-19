<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Insert', false ) ) {
	return;
}

require_once( __DIR__.'/common.php' );
require_once( dirname( __DIR__ ).'/base/insert.php' );

/**
 * @deprecated
 */
class ICWP_WPSF_Query_TrafficEntry_Insert extends ICWP_WPSF_Query_BaseInsert {
}