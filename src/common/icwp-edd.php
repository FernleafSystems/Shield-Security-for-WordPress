<?php
if ( class_exists( 'ICWP_WPSF_Edd', false ) ) {
	return;
}

class ICWP_WPSF_Edd extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Edd
	 */
	protected static $oInstance = null;

	/**
	 * @var array
	 */
	private $aAdditionalRequestParams;

	/**
	 * @return ICWP_WPSF_Edd
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * A simple outgoing POST request to see that we can communicate with the ODP servers
	 * @param string $sStoreUrl
	 * @return string
	 */
	public function ping( $sStoreUrl ) {
		$oLicense = null;

		$sStoreUrl = add_query_arg(
			array( 'license_ping' => 'Y' ),
			$sStoreUrl
		);

		$aParams = array(
			'method' => 'POST',
			'body'   => array(
				'ping'    => 'pong',
				'license' => 'abcdefghi',
				'item_id' => '123',
				'url'     => $this->loadWp()->getWpUrl()
			)
		);

		$mResponse = $this->loadFS()
						  ->requestUrl( $sStoreUrl, $aParams, true );

		$sResult = 'Unknown error communicating with license server';
		if ( is_array( $mResponse ) && !empty( $mResponse[ 'body' ] ) ) {
			$aResult = @json_decode( $mResponse[ 'body' ], true );
			$sResult = ( isset( $aResult[ 'success' ] ) && $aResult[ 'success' ] ) ? 'success' : 'unknown failure';
		}
		else if ( is_wp_error( $mResponse ) ) {
			$sResult = $mResponse->get_error_message();
		}
		return $sResult;
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return ICWP_EDD_LicenseVO|null
	 */
	public function activateLicense( $sStoreUrl, $sKey, $sItemId ) {
		return $this->commonLicenseAction( 'activate_license', $sStoreUrl, $sKey, $sItemId );
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sItemId
	 * @return ICWP_EDD_LicenseVO|null
	 */
	public function activateLicenseKeyless( $sStoreUrl, $sItemId ) {
		return $this->activateLicense( $sStoreUrl, '', $sItemId );
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return ICWP_EDD_LicenseVO|null
	 */
	public function checkLicense( $sStoreUrl, $sKey, $sItemId ) {
		return $this->commonLicenseAction( 'check_license', $sStoreUrl, $sKey, $sItemId );
	}

	/**
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return ICWP_EDD_LicenseVO|null
	 */
	public function deactivateLicense( $sStoreUrl, $sKey, $sItemId ) {
		return $this->commonLicenseAction( 'deactivate_license', $sStoreUrl, $sKey, $sItemId );
	}

	/**
	 * @param string $sAction
	 * @param string $sStoreUrl
	 * @param string $sKey
	 * @param string $sItemId
	 * @return ICWP_EDD_LicenseVO|null
	 */
	private function commonLicenseAction( $sAction, $sStoreUrl, $sKey, $sItemId ) {
		$oLicense = null;

		$aLicenseLookupParams = array(
			'timeout' => 10,
			'body'    => array_merge(
				array(
					'edd_action' => $sAction,
					'license'    => $sKey,
					'item_id'    => $sItemId,
					'url'        => $this->loadWp()->getHomeUrl(),
					'alt_url'    => $this->loadWp()->getWpUrl()
				),
				$this->getRequestParams()
			)
		);

		$aContent = $this->loadFS()
						 ->getUrl( $sStoreUrl, $aLicenseLookupParams );
		if ( !empty( $aContent ) ) {
			require_once( dirname( __FILE__ ).'/easydigitaldownloads/ICWP_EDD_LicenseVO.php' );
			$oLicense = new ICWP_EDD_LicenseVO( json_decode( $aContent[ 'body' ] ) );
		}
		return $oLicense;
	}

	/**
	 * @return array
	 */
	public function getRequestParams() {
		return is_array( $this->aAdditionalRequestParams ) ? $this->aAdditionalRequestParams : array();
	}

	/**
	 * @param array $aParams
	 * @return $this
	 */
	public function setRequestParams( $aParams = array() ) {
		$this->aAdditionalRequestParams = is_array( $aParams ) ? $aParams : array();
		return $this;
	}
}