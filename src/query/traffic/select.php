<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_TrafficEntry_Select extends ICWP_WPSF_Query_BaseSelect {

	use ICWP_WPSF_Query_TrafficEntry_Common;

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_TrafficEntryVO';
	}
}