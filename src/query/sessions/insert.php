<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_Sessions_Insert extends ICWP_WPSF_Query_BaseInsert {

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	public function create( $sSessionId, $sUsername ) {
		return $this->query();
	}
}