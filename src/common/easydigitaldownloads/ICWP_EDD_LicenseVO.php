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
	 * @param bool $bAsTimestamp
	 * @return int|string
	 */
	public function getExpiresAt( $bAsTimestamp = true ) {
		$sTime = $this->getRaw()->expires;
		return $bAsTimestamp ? strtotime( $sTime ) : $sTime;
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