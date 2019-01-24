<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\License;

/**
 * Class EddLicenseVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\License
 */
class EddLicenseVO {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

	/**
	 * @return int
	 */
	public function getActivationsLeft() {
		return $this->getParam( 'activations_left' );
	}

	/**
	 * @return string
	 */
	public function getCustomerEmail() {
		return $this->getParam( 'customer_email' );
	}

	/**
	 * @return string
	 */
	public function getChecksum() {
		return $this->getParam( 'checksum' );
	}

	/**
	 * @return string
	 */
	public function getCustomerName() {
		return $this->getParam( 'customer_name' );
	}

	/**
	 * @return int
	 */
	public function getExpiresAt() {
		$sTime = $this->getParam( 'expires' );
		return ( $sTime == 'lifetime' ) ? PHP_INT_MAX : strtotime( $sTime );
	}

	/**
	 * @return string
	 */
	public function getItemName() {
		return $this->getParam( 'item_name' );
	}

	/**
	 * @return int
	 */
	public function getLastRequestAt() {
		return (int)$this->getParam( 'last_request_at', 0 );
	}

	/**
	 * @return int
	 */
	public function getLastVerifiedAt() {
		return (int)$this->getParam( 'last_verified_at', 0 );
	}

	/**
	 * @return int
	 */
	public function getLicenseLimit() {
		return $this->getParam( 'license_limit' );
	}

	/**
	 * @return string
	 */
	public function getLicenseStatus() {
		return $this->getParam( 'license' );
	}

	/**
	 * @return int
	 */
	public function getPaymentId() {
		return $this->getParam( 'payment_id' );
	}

	/**
	 * @return int
	 */
	public function getSiteCount() {
		return $this->getParam( 'site_count' );
	}

	/**
	 * @return bool
	 */
	public function isCentral() {
		return (bool)$this->getParam( 'is_central' );
	}

	/**
	 * @return bool
	 */
	public function isSuccess() {
		return (bool)$this->getParam( 'success' );
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
	 * @return bool
	 */
	public function hasError() {
		$sE = $this->getParam( 'error' );
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
		return $this->setParam( 'last_request_at', $nAt );
	}

	/**
	 * @param bool $bAddRandom
	 * @return $this
	 */
	public function updateLastVerifiedAt( $bAddRandom = false ) {
		$nRandom = $bAddRandom ? rand( -12, 12 )*HOUR_IN_SECONDS : 0;
		return $this->setParam( 'last_verified_at', $this->getLastRequestAt() + $nRandom );
	}
}