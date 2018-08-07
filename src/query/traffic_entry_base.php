<?php

if ( class_exists( 'ICWP_WPSF_Query_TrafficEntry_Count', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_TrafficEntry_Base extends ICWP_WPSF_Query_Base {

	public function __construct() {
		$this->init();
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_TrafficEntryVO.php' );
	}
}