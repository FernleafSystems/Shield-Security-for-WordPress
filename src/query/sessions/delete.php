<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_Sessions_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginAt( $bOlderThan ) {
		return $this->query();
	}

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginIdle( $bOlderThan ) {
		return $this->query();
	}

	/**
	 * @param string $sWpUsername
	 * @return false|int
	 */
	public function forUsername( $sWpUsername ) {
		return $this->query();
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Select
	 */
	protected function getSelector() {
		return ( new ICWP_WPSF_Query_Sessions_Select() )->setTable( $this->getTable() );
	}
}