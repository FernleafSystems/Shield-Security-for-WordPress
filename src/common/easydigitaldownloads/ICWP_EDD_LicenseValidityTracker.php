<?php

/**
 * Class ICWP_EDD_LicenseValidityTracker
 */
class ICWP_EDD_LicenseValidityTracker {

	/**
	 * @var stdClass
	 */
	private $oRaw;

	/**
	 * ICWP_EDD_LicenseValidityTracker constructor.
	 * @param $aOptions
	 */
	public function __construct( $aOptions ) {
		$this->oRaw = (object)$aOptions;
	}

	/**
	 * @return string
	 */
	public function getCustomerEmail() {
		return $this->getRaw()->customer_email;
	}

	/**
	 * @return string
	 */
	public function getCustomerName() {
		return $this->getRaw()->customer_name;
	}

	/**
	 * @return int
	 */
	public function getExpiresAt() {
		$sTime = $this->getRaw()->expires;
		return ( $sTime == 'lifetime' ) ? PHP_INT_MAX : $sTime;
	}

	/**
	 * @return string
	 */
	public function getItemName() {
		return $this->getRaw()->item_name;
	}

	/**
	 * @return string
	 */
	public function getLicenseStatus() {
		return $this->getRaw()->license;
	}

	/**
	 * @return int
	 */
	public function getPaymentId() {
		return $this->getRaw()->payment_id;
	}

	/**
	 * @return stdClass
	 */
	private function getRaw() {
		return $this->oRaw;
	}

	/**
	 * @return bool
	 */
	public function isReady() {
		return isset( $this->oRaw ) && is_object( $this->oRaw ) && ( $this->getPaymentId() > 0 );
	}
}