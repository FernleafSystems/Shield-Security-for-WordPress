<?php

/**
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
		return $this->getRaw()->activations_left;
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
		return ( $sTime == 'lifetime' ) ? PHP_INT_MAX : strtotime( $sTime );
	}

	/**
	 * @return string
	 */
	public function getItemName() {
		return $this->getRaw()->item_name;
	}

	/**
	 * @return int
	 */
	public function getLicenseLimit() {
		return $this->getRaw()->license_limit;
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
	 * @return int
	 */
	public function getSiteCount() {
		return $this->getRaw()->site_count;
	}

	/**
	 * @return bool
	 */
	public function isSuccess() {
		return $this->getRaw()->success;
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
	public function hasError() {
		return isset( $this->oRaw->error );
	}

	/**
	 * @return bool
	 */
	public function hasChecksum() {
		return isset( $this->oRaw->checksum );
	}

	/**
	 * @return bool
	 */
	public function isReady() {
		return isset( $this->oRaw ) && is_object( $this->oRaw ) && $this->hasChecksum();
	}
}