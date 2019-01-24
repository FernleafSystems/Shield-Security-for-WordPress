<?php

/**
 * @deprecated v7.0.0
 * Class ICWP_EDD_LicenseVO
 */
class ICWP_EDD_LicenseVO {

	/**
	 * @var stdClass
	 */
	private $oRaw;

	/**
	 * ICWP_EDD_LicenseVO constructor.
	 * @param stdClass $oData
	 */
	public function __construct( $oData ) {
		$this->oRaw = $oData;
	}

	/**
	 * @return int
	 */
	public function getActivationsLeft() {
		return $this->getRawKey( 'activations_left' );
	}

	/**
	 * @return string
	 */
	public function getCustomerEmail() {
		return $this->getRawKey( 'customer_email' );
	}

	/**
	 * @return string
	 */
	public function getChecksum() {
		return $this->getRawKey( 'checksum' );
	}

	/**
	 * @return string
	 */
	public function getCustomerName() {
		return $this->getRawKey( 'customer_name' );
	}

	/**
	 * @return int
	 */
	public function getExpiresAt() {
		$sTime = $this->getRawKey( 'expires' );
		return ( $sTime == 'lifetime' ) ? PHP_INT_MAX : strtotime( $sTime );
	}

	/**
	 * @return string
	 */
	public function getItemName() {
		return $this->raw()->item_name;
	}

	/**
	 * @return int
	 */
	public function getLastRequestAt() {
		return (int)$this->getRawKey( 'last_request_at', 0 );
	}

	/**
	 * @return int
	 */
	public function getLastVerifiedAt() {
		return (int)$this->getRawKey( 'last_verified_at', 0 );
	}

	/**
	 * @return int
	 */
	public function getLicenseLimit() {
		return $this->getRawKey( 'license_limit' );
	}

	/**
	 * @return string
	 */
	public function getLicenseStatus() {
		return $this->getRawKey( 'license' );
	}

	/**
	 * @return int
	 */
	public function getPaymentId() {
		return $this->getRawKey( 'payment_id' );
	}

	/**
	 * @return int
	 */
	public function getSiteCount() {
		return $this->getRawKey( 'site_count' );
	}

	/**
	 * @return bool
	 */
	public function isCentral() {
		return (bool)$this->getRawKey( 'is_central' );
	}

	/**
	 * @return bool
	 */
	public function isSuccess() {
		return (bool)$this->getRawKey( 'success' );
	}

	/**
	 * @return bool
	 */
	public function isExpired() {
		return ( $this->getExpiresAt() < time() );
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return ( $this->isReady() && $this->isSuccess() && !$this->isExpired() && $this->getLicenseStatus() == 'valid' );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mDefault
	 * @return mixed
	 */
	protected function getRawKey( $sKey, $mDefault = null ) {
		$oRaw = $this->raw();
		return isset( $oRaw->{$sKey} ) ? $oRaw->{$sKey} : $mDefault;
	}

	/**
	 * IMPORTANT: uses clone
	 * @return stdClass
	 */
	public function getRaw() {
		return ( clone $this->raw() );
	}

	/**
	 * @return stdClass
	 */
	private function raw() {
		if ( !is_object( $this->oRaw ) ) {
			$this->oRaw = new stdClass();
		}
		return $this->oRaw;
	}

	/**
	 * @return bool
	 */
	public function hasError() {
		$sE = $this->getRawKey( 'error' );
		return !empty( $sE );
	}

	/**
	 * @return bool
	 */
	public function hasChecksum() {
		$sC = $this->getChecksum();
		return !empty( $sC );
	}

	/**
	 * @return bool
	 */
	public function isReady() {
		return $this->hasChecksum();
	}

	/**
	 * @param int $nAt
	 * @return $this
	 */
	public function setLastRequestAt( $nAt ) {
		return $this->setRawKey( 'last_request_at', $nAt );
	}

	/**
	 * @param bool $bAddRandom
	 * @return $this
	 */
	public function updateLastVerifiedAt( $bAddRandom = false ) {
		$nRandom = $bAddRandom ? rand( -12, 12 )*HOUR_IN_SECONDS : 0;
		return $this->setRawKey( 'last_verified_at', $this->getLastRequestAt() + $nRandom );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	protected function setRawKey( $sKey, $mValue ) {
		$this->raw()->{$sKey} = $mValue;
		return $this;
	}
}