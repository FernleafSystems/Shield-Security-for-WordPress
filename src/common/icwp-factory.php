<?php
if ( class_exists( 'ICWP_WPSF_Factory', false ) ) {
	return;
}

class ICWP_WPSF_Factory {

	/**
	 * @return ICWP_WPSF_OptionsVO
	 */
	static public function OptionsVo() {
		if ( !class_exists( 'ICWP_WPSF_OptionsVO' ) ) {
			require_once( __DIR__.'/icwp-optionsvo.php' );
		}
		return new ICWP_WPSF_OptionsVO();
	}

	/**
	 * @var ICWP_WPSF_WpCron
	 */
	protected static $oInstance = null;

	private function __construct() {
	}
}