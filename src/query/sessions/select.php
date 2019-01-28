<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

class ICWP_WPSF_Query_Sessions_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @return array
	 */
	public function all() {
		return $this->selectForUserSession();
	}

	/**
	 * @param int $nExpiredBoundary
	 * @return $this
	 */
	public function filterByLoginNotExpired( $nExpiredBoundary ) {
		return $this;
	}

	/**
	 * @param int $nExpiredBoundary
	 * @return $this
	 */
	public function filterByLoginNotIdleExpired( $nExpiredBoundary ) {
		return $this;
	}

	/**
	 * @param int $sUsername
	 * @return $this
	 */
	public function filterByUsername( $sUsername ) {
		return $this;
	}

	/**
	 * @param string $sSessionId
	 * @param string $sWpUsername
	 * @return null
	 */
	public function retrieveUserSession( $sSessionId, $sWpUsername = '' ) {
		return null;
	}

	/**
	 * @param string $sSessionId
	 * @param string $sWpUsername
	 * @return array
	 */
	protected function selectForUserSession( $sSessionId = '', $sWpUsername = '' ) {
		return [];
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_SessionVO';
	}
}