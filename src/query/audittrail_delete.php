<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Delete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_delete.php' );

class ICWP_WPSF_Query_AuditTrail_Delete extends ICWP_WPSF_Query_BaseDelete {
}