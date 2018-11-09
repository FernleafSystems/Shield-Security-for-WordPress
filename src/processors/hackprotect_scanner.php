<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Scanner', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect_Scanner extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
	}
}