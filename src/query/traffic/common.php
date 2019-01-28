<?php

require_once( dirname( dirname( __DIR__ ) ).'/lib/vendor/autoload.php' );

trait ICWP_WPSF_Query_TrafficEntry_Common {

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $bBinaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByIp( $bBinaryIp ) {
		return $this;
	}

	/**
	 * Will test whether the Binary IP can be converted back before applying filter.
	 * @param mixed $bBinaryIp - IP has already been converted using inet_pton
	 * @return $this
	 */
	public function filterByNotIp( $bBinaryIp ) {
		return $this;
	}

	/**
	 * @param bool $bIsLoggedIn - true is logged-in, false is not logged-in
	 * @return $this
	 */
	public function filterByIsLoggedIn( $bIsLoggedIn ) {
		return $this;
	}

	/**
	 * @param bool $bIsTransgression
	 * @return $this
	 */
	public function filterByIsTransgression( $bIsTransgression ) {
		return $this;
	}

	/**
	 * @param string $sTerm
	 * @return $this
	 */
	public function filterByPathContains( $sTerm ) {
		return $this;
	}

	/**
	 * @param int $nId
	 * @return $this
	 */
	public function filterByUserId( $nId ) {
		return $this;
	}

	/**
	 * @param string $sCode
	 * @return $this
	 */
	public function filterByResponseCode( $sCode ) {
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_TallyVO';
	}
}