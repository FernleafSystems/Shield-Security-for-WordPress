<?php

if ( class_exists( 'ICWP_WPSF_Query_PluginNotes_Count', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_count.php' );

class ICWP_WPSF_Query_PluginNotes_Count extends ICWP_WPSF_Query_BaseCount {
}