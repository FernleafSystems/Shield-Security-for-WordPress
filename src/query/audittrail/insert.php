<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Insert', false ) ) {
	return;
}

require_once( dirname( __DIR__ ).'/base/insert.php' );

class ICWP_WPSF_Query_AuditTrail_Insert extends ICWP_WPSF_Query_BaseInsert {
}